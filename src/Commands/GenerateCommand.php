<?php

namespace Jurager\Documentator\Commands;

use Illuminate\Console\Command;
use Illuminate\Contracts\Console\Isolatable;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Route as RouteFacade;
use Illuminate\Support\Str;
use JsonException;
use Jurager\Documentator\Formats\ResponseFormat;
use ReflectionClass;
use ReflectionNamedType;
use Throwable;

class GenerateCommand extends Command implements Isolatable
{
    private const array METHOD_COLORS = [
        'get' => 'cyan', 'post' => 'green', 'put' => 'yellow',
        'patch' => 'blue', 'delete' => 'red',
    ];

    protected $signature = 'docs:generate 
                            {--output= : Override output path}
                            {--format= : Override response format}';

    protected $description = 'Generate OpenAPI specification';

    private ResponseFormat $format;

    private array $config;

    private array $schemas = [];

    private array $fileCache = [];

    private array $usedTags = [];

    private int $processed = 0;

    /**
     * Execute the console command.
     *
     * @return int Command exit code
     */
    public function handle(): int
    {
        /** @var array<string, mixed> $config */
        $this->config = config('documentator');

        if ($formatOverride = $this->option('format')) {
            $this->config['format'] = $formatOverride;
        }

        $this->format = $this->resolveFormat();

        $this->info('Generating OpenAPI specification...');
        $this->newLine();

        try {
            $spec = $this->build();
            $path = $this->resolvePath($this->option('output') ?: $this->config['output']);

            $this->ensureDirectory($path);
            $json = json_encode(
                $this->sanitizeUtf8($spec),
                JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
            );

            if (file_put_contents($path, $json) === false) {
                $this->error("Failed to write: {$path}");

                return self::FAILURE;
            }

            $this->newLine();
            $this->info('✓ Generated successfully');
            $this->line("  <fg=gray>Format:</> {$this->format->name()}");
            $this->line("  <fg=gray>Routes:</> {$this->processed}");
            $this->line('  <fg=gray>Endpoints:</> '.count($spec['paths']));
            $this->line('  <fg=gray>Schemas:</> '.count($spec['components']['schemas']));
            $this->line('  <fg=gray>Size:</> '.round(strlen($json) / 1024, 2).' KB');
            $this->line("  <fg=gray>Output:</> {$path}");

            return self::SUCCESS;
        } catch (JsonException $e) {
            $this->error('JSON error: '.$e->getMessage());

            return self::FAILURE;
        }
    }

    /**
     * Resolve response format implementation.
     *
     *
     * @throws \InvalidArgumentException When format is unknown
     */
    private function resolveFormat(): ResponseFormat
    {
        $format = $this->config['format'];

        $formats = array_merge([
            'simple' => \Jurager\Documentator\Formats\SimpleFormat::class,
            'json-api' => \Jurager\Documentator\Formats\JsonApiFormat::class,
        ], $this->config['formats'] ?? []);

        if (class_exists($format)) {
            return new $format;
        }

        if (! isset($formats[$format])) {
            throw new \InvalidArgumentException(
                "Unknown format: {$format}. Available: ".implode(', ', array_keys($formats))
            );
        }

        return new $formats[$format];
    }

    /**
     * Build OpenAPI specification array.
     *
     * @return array<string, mixed>
     */
    private function build(): array
    {
        $paths = $this->buildPaths();

        $servers = array_map(fn ($s) => array_filter([
            'url' => rtrim($s['url'], '/'),
            'description' => $s['description'] ?? null,
        ]), $this->config['servers'] ?? [['url' => config('app.url', 'http://localhost')]]);

        $spec = [
            'openapi' => '3.0.3',
            'info' => array_filter([
                'title' => $this->config['title'],
                'version' => $this->config['version'],
                'description' => $this->config['description'] ?? $this->format->description(),
            ]),
            'servers' => $servers,
            'tags' => $this->buildTags(),
            'paths' => $paths,
            'components' => [
                'schemas' => array_merge($this->format->schemas(), $this->schemas),
                'responses' => $this->format->responses(),
                'securitySchemes' => $this->config['security']['schemes'] ?? [],
            ],
        ];

        if (! empty($this->config['security']['default'])) {
            $spec['security'] = array_map(fn ($scheme) => [$scheme => []], $this->config['security']['default']);
        }

        if (empty($spec['components']['securitySchemes'])) {
            unset($spec['components']['securitySchemes']);
        }

        return $spec;
    }

    /**
     * Build OpenAPI tags section.
     *
     * @return array<int, array{name: string, description?: string}>
     */
    private function buildTags(): array
    {
        $configured = $this->config['tags'] ?? [];
        $tags = [];

        foreach ($configured as $name => $description) {
            $tags[$name] = ['name' => $name, 'description' => $description];
        }

        foreach ($this->usedTags as $name => $_) {
            $tags[$name] ??= ['name' => $name];
        }

        return array_values($tags);
    }

    /**
     * Build OpenAPI paths from Laravel routes.
     *
     * @return array<string, array<string, mixed>>
     */
    private function buildPaths(): array
    {
        $routes = collect(RouteFacade::getRoutes())
            ->filter(fn (Route $r) => $this->shouldInclude($r))
            ->values();

        if ($routes->isEmpty()) {
            $this->warn('No routes found matching criteria');

            return [];
        }

        $methods = $this->config['methods'];
        $total = $routes->count();
        $paths = [];

        foreach ($routes as $route) {
            $this->processed++;
            $routeMethods = array_values(array_intersect(array_map('strtolower', $route->methods()), $methods));

            if (empty($routeMethods)) {
                continue;
            }

            $this->outputProgress($route, $routeMethods, $total);

            $path = '/'.ltrim(preg_replace('/\{([^}]+)\?}/', '{$1}', $route->uri()), '/');

            foreach ($routeMethods as $method) {
                $paths[$path][$method] = $this->buildOperation($route, $method);
            }
        }

        $this->fileCache = [];
        ksort($paths);

        return $paths;
    }

    private function shouldInclude(Route $route): bool
    {
        $uri = $route->uri();
        $cfg = $this->config['routes'];

        foreach ($cfg['exclude'] ?? [] as $pattern) {
            if (Str::is($pattern, $uri)) {
                return false;
            }
        }

        foreach ($cfg['exclude_middleware'] ?? [] as $m) {
            if (in_array($m, $route->middleware())) {
                return false;
            }
        }

        $includes = $cfg['include'] ?? [];

        if (empty($includes)) {
            return true;
        }

        foreach ($includes as $pattern) {
            if (Str::is($pattern, $uri)) {
                return true;
            }
        }

        return false;
    }

    private function outputProgress(Route $route, array $methods, int $total): void
    {
        $methodStr = implode(' ', array_map(
            fn ($m) => sprintf('<fg=%s>%s</>', self::METHOD_COLORS[$m] ?? 'white', str_pad(strtoupper($m), 6)),
            $methods
        ));

        $this->line(sprintf(
            '  <fg=gray>[%s/%d]</> %s <fg=white>%s</>',
            str_pad((string) $this->processed, strlen((string) $total), ' ', STR_PAD_LEFT),
            $total,
            $methodStr,
            $route->uri()
        ));
    }

    /**
     * Build OpenAPI operation for route & HTTP method.
     *
     * @return array<string, mixed>
     */
    private function buildOperation(Route $route, string $method): array
    {
        $doc = $this->extractDoc($route);
        $segments = $this->pathSegments($route);
        $tags = $this->extractTags($route, $doc);

        foreach ($tags as $tag) {
            $this->usedTags[$tag] = true;
        }

        $operation = array_filter([
            'operationId' => $this->operationId($route, $method, $segments),
            'summary' => $doc['summary'] ?? $this->generateSummary($route, $method, $segments),
            'description' => $doc['description'] ?? null,
            'tags' => $tags,
            'deprecated' => $doc['deprecated'] ?? null,
            'parameters' => $this->buildParameters($route, $method, $doc) ?: null,
            'requestBody' => in_array($method, ['post', 'put', 'patch'])
                ? $this->buildRequestBody($route, $method, $doc, $segments)
                : null,
            'responses' => $this->buildResponses($doc),
        ]);

        foreach ($this->config['default_responses'] ?? [] as $status => $response) {
            $operation['responses'][$status] ??= $response;
        }

        if (isset($doc['authenticated']) && $doc['authenticated'] === false) {
            $operation['security'] = [];
        }

        return $operation;
    }

    private function operationId(Route $route, string $method, array $segments): string
    {
        if ($name = $route->getName()) {
            return str_replace('.', '_', $name);
        }

        return strtolower($method).'_'.Str::snake(implode('_', $segments) ?: 'root');
    }

    /**
     * Build OpenAPI parameters section.
     *
     * @param  array<string, mixed>  $doc
     * @return array<int, array<string, mixed>>
     */
    private function buildParameters(Route $route, string $method, array $doc): array
    {
        $params = [];

        foreach ($route->parameterNames() as $name) {
            $urlParam = collect($doc['urlParams'] ?? [])->firstWhere('name', $name);
            $params[] = [
                'name' => $name,
                'in' => 'path',
                'required' => $urlParam['required'] ?? true,
                'schema' => ['type' => $this->normalizeType($urlParam['type'] ?? 'string')],
                'description' => $urlParam['description'] ?? $this->trans('id_of', ['name' => Str::headline($name)]),
            ];
        }

        if ($method === 'get') {
            foreach ($doc['queryParams'] ?? [] as $p) {
                $name = str_contains($p['name'], '.')
                    ? explode('.', $p['name'])[0].'['.implode('][', array_slice(explode('.', $p['name']), 1)).']'
                    : $p['name'];

                $params[] = [
                    'name' => $name,
                    'in' => 'query',
                    'required' => $p['required'],
                    'schema' => ['type' => $this->normalizeType($p['type'])],
                    'description' => $p['description'],
                ];
            }
        }

        return $params;
    }

    /**
     * Build OpenAPI requestBody schema.
     *
     * @param  array<string, mixed>  $doc
     * @param  array<int, string>  $segments
     * @return array<string, mixed>|null
     */
    private function buildRequestBody(Route $route, string $method, array $doc, array $segments): ?array
    {
        $rules = $this->extractValidationRules($route);
        $schema = $this->buildSchema($rules, $doc);

        foreach ($route->parameterNames() as $param) {
            unset($schema['properties'][$param]);
            if (isset($schema['required'])) {
                $schema['required'] = array_values(array_diff($schema['required'], [$param]));
            }
        }

        if (empty($schema['properties'])) {
            return null;
        }

        $resource = $doc['resource'] ?? Str::singular(Str::snake($segments ? end($segments) : 'resource'));
        $schemaName = Str::studly("{$resource}_{$this->operationId($route, $method, $segments)}_Request");
        $this->schemas[$schemaName] = $schema;

        return [
            'required' => true,
            'content' => ['application/json' => ['schema' => ['$ref' => "#/components/schemas/{$schemaName}"]]],
        ];
    }

    /**
     * Build JSON schema from validation rules and doc params.
     *
     * @param  array<string, mixed>  $rules
     * @param  array<string, mixed>  $doc
     * @return array<string, mixed>
     */
    private function buildSchema(array $rules, array $doc): array
    {
        $props = [];
        $required = [];

        foreach ($rules as $field => $r) {
            $ruleList = is_array($r) ? $r : explode('|', $r);
            $isRequired = in_array('required', $ruleList);

            if (str_contains($field, '.*.')) {
                [$arrayField, , $itemField] = explode('.', $field, 3);
                $props[$arrayField] ??= [
                    'type' => 'array',
                    'description' => Str::headline($arrayField),
                    'items' => ['type' => 'object', 'properties' => [], 'required' => []],
                ];
                $props[$arrayField]['items']['properties'][$itemField] = $this->fieldSchema($itemField, $ruleList);
                if ($isRequired) {
                    $props[$arrayField]['items']['required'][] = $itemField;
                }

                continue;
            }

            if (str_contains($field, '.')) {
                continue;
            }

            if ($isRequired) {
                $required[] = $field;
            }
            $props[$field] = $this->fieldSchema($field, $ruleList);
        }

        foreach ($doc['bodyParams'] ?? [] as $p) {
            if (str_contains($p['name'], '.')) {
                [$arrayField, $itemField] = explode('.', $p['name'], 2);
                $props[$arrayField] ??= [
                    'type' => 'array',
                    'description' => Str::headline($arrayField),
                    'items' => ['type' => 'object', 'properties' => [], 'required' => []],
                ];
                $props[$arrayField]['items']['properties'][$itemField] = [
                    'type' => $this->normalizeType($p['type']),
                    'description' => $p['description'] ?: Str::headline($itemField),
                ];
                if ($p['required']) {
                    $props[$arrayField]['items']['required'][] = $itemField;
                }
            } else {
                if ($p['required'] && ! in_array($p['name'], $required)) {
                    $required[] = $p['name'];
                }
                $props[$p['name']] = [
                    'type' => $this->normalizeType($p['type']),
                    'description' => $p['description'] ?: Str::headline($p['name']),
                ];
            }
        }

        return array_filter(['type' => 'object', 'properties' => $props, 'required' => $required ?: null]);
    }

    /**
     * Build schema for a single field.
     *
     * @param  array<int, string>  $rules
     * @return array<string, mixed>
     */
    private function fieldSchema(string $field, array $rules): array
    {
        $type = 'string';
        foreach ($rules as $rule) {
            $name = explode(':', $rule)[0];
            if (isset($this->config['type_map'][$name])) {
                $type = $this->config['type_map'][$name];
                break;
            }
        }

        $schema = ['type' => $type, 'description' => $this->fieldDescription($field, $rules)];

        foreach ($rules as $rule) {
            match (true) {
                str_starts_with($rule, 'max:') => $schema[$type === 'string' ? 'maxLength' : 'maximum'] = (int) substr($rule, 4),
                str_starts_with($rule, 'min:') => $schema[$type === 'string' ? 'minLength' : 'minimum'] = (int) substr($rule, 4),
                str_starts_with($rule, 'in:') => $schema['enum'] = explode(',', substr($rule, 3)),
                $rule === 'email' => $schema['format'] = 'email',
                $rule === 'url' => $schema['format'] = 'uri',
                $rule === 'uuid' => $schema['format'] = 'uuid',
                $rule === 'date' => $schema['format'] = 'date',
                $rule === 'nullable' => $schema['nullable'] = true,
                default => null,
            };
        }

        return $schema;
    }

    private function fieldDescription(string $field, array $rules): string
    {
        $desc = [];

        foreach ($rules as $rule) {
            match (true) {
                str_starts_with($rule, 'max:') => $desc[] = $this->trans('max', ['value' => substr($rule, 4)]),
                str_starts_with($rule, 'min:') => $desc[] = $this->trans('min', ['value' => substr($rule, 4)]),
                $rule === 'email' => $desc[] = 'email',
                str_starts_with($rule, 'unique') => $desc[] = $this->trans('unique'),
                str_starts_with($rule, 'exists:') => $desc[] = $this->trans('exists'),
                default => null,
            };
        }

        return $desc ? Str::headline($field).' ('.implode(', ', $desc).')' : Str::headline($field);
    }

    private function buildResponses(array $doc): array
    {
        if (empty($doc['responses'])) {
            return ['200' => ['description' => 'Success']];
        }

        $statusDesc = $this->config['status_descriptions'] ?? [];
        $responses = [];

        foreach ($doc['responses'] as $r) {
            $status = (string) $r['status'];
            $responses[$status] = [
                'description' => $statusDesc[$r['status']] ?? "HTTP {$status}",
                'content' => ['application/json' => [
                    'schema' => is_array($r['content']) ? ['example' => $r['content']] : ['type' => 'string', 'example' => $r['content']],
                ]],
            ];
        }

        return $responses;
    }

    /**
     * Extract parsed PHPDoc info from controller action.
     *
     * @return array<string, mixed>
     */
    private function extractDoc(Route $route): array
    {
        $action = $route->getActionName();

        if ($action === 'Closure' || ! str_contains($action, '@')) {
            return [];
        }

        [$class, $method] = explode('@', $action, 2);

        if (! class_exists($class)) {
            return [];
        }

        try {

            $ref = $this->reflect($class);

            if (! $ref->hasMethod($method)) {
                return [];
            }

            $docComment = $ref->getMethod($method)->getDocComment();

            return $docComment ? $this->parseDoc($docComment) : [];
        } catch (Throwable) {
            return [];
        }
    }

    /**
     * Parse raw PHPDoc block.
     *
     * @return array<string, mixed>
     */
    private function parseDoc(string $doc): array
    {
        $info = [
            'summary' => null,
            'description' => null,
            'group' => null,
            'resource' => null,
            'deprecated' => null,
            'authenticated' => null,
            'queryParams' => [],
            'bodyParams' => [],
            'urlParams' => [],
            'responses' => [],
        ];

        $lines = array_values(array_filter(
            array_map(
                static fn ($l) => ltrim(trim($l), "* \t"),
                preg_split('/\R/', $doc) ?: []
            ),
            static fn ($l) => $l !== '' && $l !== '/**' && $l !== '*/'
        ));

        $summary = null;
        $descriptionLines = [];
        $parsingText = true;

        foreach ($lines as $line) {

            if (str_starts_with($line, '@')) {
                $parsingText = false;
            }

            if ($parsingText) {
                if ($summary === null) {
                    $summary = $line;
                } else {
                    $descriptionLines[] = $line;
                }

                continue;
            }

            if (preg_match('/^@summary\s+(.+)$/i', $line, $m)) {
                $info['summary'] = trim($m[1]);

                continue;
            }

            if (preg_match('/^@description\s*(.*)$/i', $line, $m)) {
                if ($m[1] !== '') {
                    $descriptionLines[] = trim($m[1]);
                }

                continue;
            }

            if (preg_match('/^@group\s+(.+)$/i', $line, $m)) {
                $info['group'] = trim($m[1]);

                continue;
            }

            if (preg_match('/^@resource\s+(\S+)/i', $line, $m)) {
                $info['resource'] = trim($m[1]);

                continue;
            }

            if (preg_match('/^@deprecated\b/i', $line)) {
                $info['deprecated'] = true;

                continue;
            }

            if (preg_match('/^@authenticated\b/i', $line)) {
                $info['authenticated'] = true;

                continue;
            }

            if (preg_match('/^@unauthenticated\b/i', $line)) {
                $info['authenticated'] = false;

                continue;
            }

            if (preg_match('/^@response(?:\s+(\d+))?\s+(.+)$/i', $line, $m)) {
                $content = trim($m[2]);
                $decoded = json_decode($content, true);

                $info['responses'][] = [
                    'status' => (int) ($m[1] ?: 200),
                    'content' => json_last_error() === JSON_ERROR_NONE ? $decoded : $content,
                ];

                continue;
            }

            foreach ([
                'queryParam' => 'queryParams',
                'bodyParam' => 'bodyParams',
                'urlParam' => 'urlParams',
            ] as $tag => $key) {
                if (preg_match("/^@{$tag}\s+(.+)$/i", $line, $m)) {
                    if ($param = $this->parseParam($m[1])) {
                        $info[$key][] = $param;
                    }

                    continue 2;
                }
            }
        }

        if ($info['summary'] === null && $summary !== null) {
            $info['summary'] = $summary;
        }

        if ($descriptionLines) {
            $info['description'] = trim(implode("\n", $descriptionLines));
        }

        return array_filter(
            $info,
            static fn ($v) => $v !== null && $v !== []
        );
    }

    /**
     * Parse @queryParam / @bodyParam / @urlParam definition.
     *
     * @return array{name: string, type: string, required: bool, description: string}|null
     */
    private function parseParam(string $text): ?array
    {
        $parts = preg_split('/\s+/', $text, 4);
        if (empty($parts[0])) {
            return null;
        }

        $required = isset($parts[2]) && strtolower($parts[2]) === 'required';

        return [
            'name' => $parts[0],
            'type' => $parts[1] ?? 'string',
            'required' => $required,
            'description' => trim($required ? ($parts[3] ?? '') : ($parts[2] ?? '')),
        ];
    }

    private function extractValidationRules(Route $route): array
    {
        $action = $route->getActionName();

        if ($action === 'Closure' || ! str_contains($action, '@')) {
            return [];
        }

        [$class, $methodName] = explode('@', $action, 2);

        if (! class_exists($class)) {
            return [];
        }

        try {
            $ref = $this->reflect($class);

            if (! $ref->hasMethod($methodName)) {
                return [];
            }

            $method = $ref->getMethod($methodName);

            foreach ($method->getParameters() as $param) {

                $type = $param->getType();

                if (! $type instanceof ReflectionNamedType) {
                    continue;
                }

                $typeName = $type->getName();

                if (! class_exists($typeName) || ! is_subclass_of($typeName, 'Illuminate\Foundation\Http\FormRequest')) {
                    continue;
                }

                $formRef = new ReflectionClass($typeName);

                if (! $formRef->hasMethod('rules')) {
                    continue;
                }

                $rules = $formRef->newInstanceWithoutConstructor()->rules();

                if (is_array($rules)) {
                    return $rules;
                }
            }

            $file = $ref->getFileName();

            if (! $file || ! file_exists($file)) {
                return [];
            }

            $this->fileCache[$file] ??= file_get_contents($file) ?: '';

            $lines = explode("\n", $this->fileCache[$file]);
            $code = implode("\n", array_slice($lines, $method->getStartLine() - 1, $method->getEndLine() - $method->getStartLine() + 1));

            if (preg_match('/\$\w+->validate\(\s*\[(.*?)]\s*\)/s', $code, $m)) {
                preg_match_all('/[\'"]([^\'"]+)[\'"]\s*=>\s*[\'"]([^\'"]+)[\'"]/', $m[1], $matches, PREG_SET_ORDER);

                return array_column($matches, 2, 1);
            }
        } catch (Throwable) {
        }

        return [];
    }

    private function extractTags(Route $route, array $doc): array
    {
        if (! empty($doc['group'])) {
            return [$doc['group']];
        }

        $action = $route->getActionName();

        if (str_contains($action, '@')) {
            return [Str::of(class_basename(explode('@', $action)[0]))->replace('Controller', '')->headline()->toString()];
        }

        $segments = explode('/', trim($route->uri(), '/'), 2);

        return [Str::headline($segments[0] ?? 'Default')];
    }

    /**
     * Generate default operation summary.
     *
     * @param  array<int, string>  $segments
     */
    private function generateSummary(Route $route, string $method, array $segments): string
    {
        if ($name = $route->getName()) {
            return Str::headline(str_replace('.', ' ', $name));
        }

        $resource = Str::singular(
            Str::snake($segments ? end($segments) : 'resource')
        );

        $uriSegments = explode('/', trim($route->uri(), '/'));
        $isCollection = ! str_starts_with($uriSegments ? end($uriSegments) : '', '{');

        return match ($method) {
            'get' => __('api.'.($isCollection ? 'list' : 'get'), ['resource' => $resource]),
            'post' => __('api.create', ['resource' => $resource]),
            'put',
            'patch' => __('api.update', ['resource' => $resource]),
            'delete' => __('api.delete', ['resource' => $resource]),
            default => $route->uri(),
        };
    }

    private function pathSegments(Route $route): array
    {
        return array_values(array_filter(explode('/', $route->uri()), fn ($s) => ! str_starts_with($s, '{')));
    }

    private function normalizeType(string $type): string
    {
        return $this->config['type_map'][strtolower($type)]
            ?? $this->config['type_map']['string']
            ?? 'string';
    }

    private function resolvePath(string $path): string
    {
        if (str_starts_with($path, '/') || preg_match('#^[A-Za-z]:[/\\\\]#', $path)) {
            return $path;
        }

        return base_path($path);
    }

    private function ensureDirectory(string $path): void
    {
        $dir = dirname($path);

        if (! is_dir($dir) && ! mkdir($dir, 0755, true) && ! is_dir($dir)) {
            throw new \RuntimeException("Cannot create directory: {$dir}");
        }
    }

    /**
     * @throws \ReflectionException
     */
    private function reflect(string $class): ReflectionClass
    {
        return $this->reflectionCache[$class]
            ??= new ReflectionClass($class);
    }

    /**
     * Recursively sanitize data to valid UTF-8.
     */
    private function sanitizeUtf8(mixed $data): mixed
    {
        if (is_string($data)) {
            if (! mb_check_encoding($data, 'UTF-8')) {
                $data = mb_convert_encoding($data, 'UTF-8');
            }

            if (class_exists(\Normalizer::class)) {
                return \Normalizer::normalize($data, \Normalizer::FORM_C);
            }

            return $data;
        }

        if (is_array($data)) {
            return array_map($this->sanitizeUtf8(...), $data);
        }

        return $data;
    }
}
