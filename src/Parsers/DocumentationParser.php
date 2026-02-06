<?php

namespace Jurager\Documentator\Parsers;

use Illuminate\Routing\Route;
use Illuminate\Support\Str;
use Jurager\Documentator\Resolvers\FieldTypeResolver;
use ReflectionClass;
use ReflectionMethod;
use ReflectionNamedType;
use Throwable;

/**
 * Parses documentation from PHP code including PHPDoc comments, validation rules, and resource definitions.
 */
class DocumentationParser
{
    private array $resourceCache = [];

    private array $reflectionCache = [];

    private array $fileCache = [];

    private array $methodCache = [];

    /**
     * @param  FieldTypeResolver  $typeResolver  Field type resolver for resource attributes
     */
    public function __construct(
        private FieldTypeResolver $typeResolver
    ) {
    }

    /**
     * Parse PHPDoc comment block.
     *
     * @param  string  $doc  PHPDoc comment string
     * @return array Parsed documentation with summary, description, params, responses, etc.
     */
    public function parseDocComment(string $doc): array
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

        $lines = $this->extractLines($doc);

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

            // Parse tags
            if ($this->parseSummaryTag($line, $info)) {
                continue;
            }

            if ($this->parseDescriptionTag($line, $descriptionLines)) {
                continue;
            }

            if ($this->parseGroupTag($line, $info)) {
                continue;
            }

            if ($this->parseResourceTag($line, $info)) {
                continue;
            }

            if ($this->parseDeprecatedTag($line, $info)) {
                continue;
            }

            if ($this->parseAuthenticatedTag($line, $info)) {
                continue;
            }

            if ($this->parseResponseTag($line, $info)) {
                continue;
            }

            if ($this->parseParamTags($line, $info)) {
                continue;
            }
        }

        // Set summary and description
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
     * Extract validation rules from route controller method.
     *
     * @param  Route  $route  Laravel route instance
     * @return array Validation rules array
     */
    public function extractValidation(Route $route): array
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

            // Try to extract from FormRequest parameter
            if ($rules = $this->extractFromFormRequest($method)) {
                return $rules;
            }

            // Try to extract from validate() call in method body
            return $this->extractFromMethodBody($method);
        } catch (Throwable) {
            return [];
        }
    }

    /**
     * Find and parse resource class for a given model/route.
     *
     * @param  string  $resourceClass  Fully qualified resource class name
     * @return array|null Parsed resource with attributes and relationships, or null if not found
     */
    public function parseResource(string $resourceClass): ?array
    {
        if (isset($this->resourceCache[$resourceClass])) {
            return $this->resourceCache[$resourceClass];
        }

        if (! class_exists($resourceClass)) {
            return null;
        }

        try {
            $ref = new ReflectionClass($resourceClass);

            $result = [
                'attributes' => $this->extractAttributes($ref),
                'relationships' => $this->extractRelationships($ref),
            ];

            $this->resourceCache[$resourceClass] = $result;

            return $result;
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * Find resource class by controller action.
     *
     * @param  string  $controllerClass  Fully qualified controller class name
     * @param  string  $method  Controller method name
     * @return string|null Fully qualified resource class name, or null if not found
     */
    public function findResourceClass(string $controllerClass, string $method): ?string
    {
        if (! class_exists($controllerClass)) {
            return null;
        }

        try {
            $ref = new ReflectionClass($controllerClass);

            if (! $ref->hasMethod($method)) {
                return null;
            }

            $methodRef = $ref->getMethod($method);

            // Check return type
            $returnType = $methodRef->getReturnType();

            if ($returnType instanceof ReflectionNamedType) {
                $typeName = $returnType->getName();

                if ($this->isResourceClass($typeName)) {
                    return $typeName;
                }
            }

            // Parse method body for Resource::make() or Resource::collection()
            $methodCode = $this->getMethodSource($methodRef);

            // Match Resource::make() or Resource::collection() or new Resource()
            if (preg_match('/(\w+Resource)::(?:make|collection)\s*\(/', $methodCode, $m)) {
                return $this->resolveResourceClass($m[1], $ref);
            }

            if (preg_match('/new\s+(\w+Resource)\s*\(/', $methodCode, $m)) {
                return $this->resolveResourceClass($m[1], $ref);
            }

            // Match return $this->response(..., XxxResource::class)
            if (preg_match('/(\w+Resource)::class/', $methodCode, $m)) {
                return $this->resolveResourceClass($m[1], $ref);
            }

        } catch (Throwable) {
        }

        return null;
    }

    /**
     * Guess resource class from route/model name.
     *
     * @param  string  $modelName  Model or resource name
     * @param  array  $resourceNamespaces  Additional namespaces to search
     * @return string|null Fully qualified resource class name, or null if not found
     */
    public function guessResourceClass(string $modelName, array $resourceNamespaces = []): ?string
    {
        $baseName = Str::studly(Str::singular($modelName));
        $resourceName = $baseName.'Resource';

        $namespaces = array_merge([
            'App\\Http\\Resources\\',
            'App\\Http\\Resources\\'.$baseName.'\\',
        ], $resourceNamespaces);

        foreach ($namespaces as $ns) {
            $class = rtrim($ns, '\\').'\\'.$resourceName;

            if (class_exists($class) && $this->isResourceClass($class)) {
                return $class;
            }
        }

        return null;
    }

    /**
     * Extract clean lines from PHPDoc comment.
     *
     * @param string $doc PHPDoc comment string
     * @return array Clean lines without asterisks and comment markers
     */
    private function extractLines(string $doc): array
    {
        return array_values(array_filter(
            array_map(
                static fn ($l) => ltrim(trim($l), "* \t"),
                preg_split('/\R/u', $doc) ?: []
            ),
            static fn ($l) => $l !== '' && $l !== '/**' && $l !== '*/'
        ));
    }

    /**
     * Parse @summary tag from PHPDoc line.
     *
     * @param string $line PHPDoc line
     * @param array $info Reference to info array to update
     * @return bool True if tag was parsed
     */
    private function parseSummaryTag(string $line, array &$info): bool
    {
        if (preg_match('/^@summary\s+(.+)$/iu', $line, $m)) {
            $info['summary'] = trim($m[1]);

            return true;
        }

        return false;
    }

    /**
     * Parse @description tag from PHPDoc line.
     *
     * @param string $line PHPDoc line
     * @param array $descriptionLines Reference to description lines array
     * @return bool True if tag was parsed
     */
    private function parseDescriptionTag(string $line, array &$descriptionLines): bool
    {
        if (preg_match('/^@description\s*(.*)$/iu', $line, $m)) {
            if ($m[1] !== '') {
                $descriptionLines[] = trim($m[1]);
            }

            return true;
        }

        return false;
    }

    /**
     * Parse @group tag from PHPDoc line.
     *
     * @param string $line PHPDoc line
     * @param array $info Reference to info array
     * @return bool True if tag was parsed
     */
    private function parseGroupTag(string $line, array &$info): bool
    {
        if (preg_match('/^@group\s+(.+)$/iu', $line, $m)) {
            $info['group'] = trim($m[1]);

            return true;
        }

        return false;
    }

    /**
     * Parse @resource tag from PHPDoc line.
     *
     * @param string $line PHPDoc line
     * @param array $info Reference to info array
     * @return bool True if tag was parsed
     */
    private function parseResourceTag(string $line, array &$info): bool
    {
        if (preg_match('/^@resource\s+(\S+)/iu', $line, $m)) {
            $info['resource'] = trim($m[1]);

            return true;
        }

        return false;
    }

    /**
     * Parse @deprecated tag from PHPDoc line.
     *
     * @param string $line PHPDoc line
     * @param array $info Reference to info array
     * @return bool True if tag was parsed
     */
    private function parseDeprecatedTag(string $line, array &$info): bool
    {
        if (preg_match('/^@deprecated\b/i', $line)) {
            $info['deprecated'] = true;

            return true;
        }

        return false;
    }

    /**
     * Parse @authenticated and @unauthenticated tags from PHPDoc line.
     *
     * @param string $line PHPDoc line
     * @param array $info Reference to info array
     * @return bool True if tag was parsed
     */
    private function parseAuthenticatedTag(string $line, array &$info): bool
    {
        if (preg_match('/^@authenticated\b/i', $line)) {
            $info['authenticated'] = true;

            return true;
        }

        if (preg_match('/^@unauthenticated\b/i', $line)) {
            $info['authenticated'] = false;

            return true;
        }

        return false;
    }

    /**
     * Parse @response tag from PHPDoc line.
     *
     * @param string $line PHPDoc line
     * @param array $info Reference to info array
     * @return bool True if tag was parsed
     */
    private function parseResponseTag(string $line, array &$info): bool
    {
        if (preg_match('/^@response(?:\s+(\d+))?\s+(.+)$/iu', $line, $m)) {
            $content = trim($m[2]);
            $decoded = json_decode($content, true);

            $info['responses'][] = [
                'status' => (int) ($m[1] ?: 200),
                'content' => json_last_error() === JSON_ERROR_NONE ? $decoded : $content,
            ];

            return true;
        }

        return false;
    }

    /**
     * Parse @queryParam, @bodyParam, @urlParam tags from PHPDoc line.
     *
     * @param string $line PHPDoc line
     * @param array $info Reference to info array
     * @return bool True if tag was parsed
     */
    private function parseParamTags(string $line, array &$info): bool
    {
        foreach ([
            'queryParam' => 'queryParams',
            'bodyParam' => 'bodyParams',
            'urlParam' => 'urlParams',
        ] as $tag => $key) {
            if (preg_match("/^@$tag\s+(.+)$/iu", $line, $m)) {
                if ($param = $this->parseParam($m[1])) {
                    $info[$key][] = $param;
                }

                return true;
            }
        }

        return false;
    }

    /**
     * Parse parameter definition from text.
     * Format: name type [required] description
     *
     * @param string $text Parameter definition text
     * @return array|null Parsed parameter or null
     */
    private function parseParam(string $text): ?array
    {
        $parts = preg_split('/\s+/u', $text, 4);

        if (empty($parts[0])) {
            return null;
        }

        $hasRequiredFlag = isset($parts[2]) && in_array(strtolower($parts[2]), ['required', 'optional']);
        $required = isset($parts[2]) && strtolower($parts[2]) === 'required';

        return [
            'name' => $parts[0],
            'type' => $parts[1] ?? 'string',
            'required' => $required,
            'description' => trim($hasRequiredFlag ? ($parts[3] ?? '') : ($parts[2] ?? '')),
        ];
    }

    /**
     * Extract validation rules from FormRequest type hint.
     *
     * @param ReflectionMethod $method Controller method reflection
     * @return array Validation rules array
     */
    private function extractFromFormRequest(ReflectionMethod $method): array
    {
        foreach ($method->getParameters() as $param) {
            $type = $param->getType();

            if (! $type || $type->isBuiltin()) {
                continue;
            }

            $typeName = $type->getName();

            if (! class_exists($typeName)) {
                continue;
            }

            try {
                $requestRef = new ReflectionClass($typeName);

                if (! $requestRef->hasMethod('rules')) {
                    continue;
                }

                $rulesMethod = $requestRef->getMethod('rules');

                if (! $rulesMethod->isPublic()) {
                    continue;
                }

                $request = $requestRef->newInstanceWithoutConstructor();
                $rules = $rulesMethod->invoke($request);

                if (is_array($rules)) {
                    return $rules;
                }
            } catch (Throwable) {
                continue;
            }
        }

        return [];
    }

    /**
     * Extract validation rules from $request->validate() call in method body.
     *
     * @param ReflectionMethod $method Controller method reflection
     * @return array Validation rules array
     */
    private function extractFromMethodBody(ReflectionMethod $method): array
    {
        $code = $this->getMethodSource($method);

        if (preg_match('/\$\w+->validate\(\s*\[(.*?)][\s,)]/s', $code, $m)) {
            preg_match_all('/[\'"]([^\'\"]+)[\'\"]\s*=>\s*[\'"]([^\'\"]+)[\'"]/', $m[1], $matches, PREG_SET_ORDER);

            return array_column($matches, 2, 1);
        }

        return [];
    }

    /**
     * Extract attributes from resource class.
     *
     * @param ReflectionClass $ref Resource class reflection
     * @return array Attributes array with types
     */
    private function extractAttributes(ReflectionClass $ref): array
    {
        $attributes = [];

        // Check for $attributes property
        if ($ref->hasProperty('attributes')) {
            $prop = $ref->getProperty('attributes');

            if ($prop->isPublic()) {
                try {
                    $instance = $ref->newInstanceWithoutConstructor();
                    $value = $prop->getValue($instance);

                    if (is_array($value)) {
                        foreach ($value as $attr) {
                            if (is_string($attr)) {
                                $attributes[$attr] = ['type' => $this->typeResolver->fromFieldName($attr)];
                            }
                        }
                    }
                } catch (Throwable) {
                }
            }
        }

        // Check toArray() method for additional attributes
        if ($ref->hasMethod('toArray')) {
            $method = $ref->getMethod('toArray');
            $attributes = array_merge($attributes, $this->parseToArrayMethod($ref, $method));
        }

        return $attributes;
    }

    /**
     * Extract relationships from resource class.
     *
     * @param ReflectionClass $ref Resource class reflection
     * @return array Relationships array with types and resource classes
     */
    private function extractRelationships(ReflectionClass $ref): array
    {
        $relationships = [];

        // Check toRelationships() method
        if ($ref->hasMethod('toRelationships')) {
            $method = $ref->getMethod('toRelationships');
            $relationships = $this->parseRelationshipsMethod($ref, $method);
        }

        return $relationships;
    }

    /**
     * Parse toArray() method for attribute definitions.
     *
     * @param ReflectionClass $ref Resource class reflection
     * @param ReflectionMethod $method toArray method reflection
     * @return array Parsed attributes array
     */
    private function parseToArrayMethod(ReflectionClass $ref, ReflectionMethod $method): array
    {
        $attributes = [];

        try {
            $methodCode = $this->getMethodSource($method);

            // Match array keys: 'key' => ... or "key" => ...
            preg_match_all("/['\"](\w+)['\"]\s*=>/", $methodCode, $matches);

            foreach ($matches[1] as $attr) {
                if (! isset($attributes[$attr])) {
                    $attributes[$attr] = ['type' => $this->typeResolver->fromFieldName($attr)];
                }
            }
        } catch (Throwable) {
        }

        return $attributes;
    }

    /**
     * Parse toRelationships() method for relationship definitions.
     *
     * @param ReflectionClass $ref Resource class reflection
     * @param ReflectionMethod $method toRelationships method reflection
     * @return array Parsed relationships array
     */
    private function parseRelationshipsMethod(ReflectionClass $ref, ReflectionMethod $method): array
    {
        $relationships = [];

        try {
            $methodCode = $this->getMethodSource($method);

            // Match: 'relationName' => fn() => XxxResource::make(...) or ::collection(...)
            preg_match_all(
                "/['\"](\w+)['\"]\s*=>\s*(?:fn\s*\(\)\s*=>)?\s*(\w+Resource)::(\w+)/",
                $methodCode,
                $matches,
                PREG_SET_ORDER
            );

            foreach ($matches as $match) {
                $name = $match[1];
                $resourceClass = $match[2];
                $methodName = $match[3];

                $relationships[$name] = [
                    'type' => Str::snake($name),
                    'resource' => $this->resolveResourceClass($resourceClass, $ref),
                    'collection' => $methodName === 'collection',
                ];
            }
        } catch (Throwable) {
        }

        return $relationships;
    }

    /**
     * Resolve short resource class name to fully qualified class name.
     *
     * @param string $shortName Short class name (e.g., UserResource)
     * @param ReflectionClass $contextRef Context class reflection for namespace resolution
     * @return string|null Fully qualified class name or null
     */
    private function resolveResourceClass(string $shortName, ReflectionClass $contextRef): ?string
    {
        // Check if it's already FQCN
        if (class_exists($shortName)) {
            return $shortName;
        }

        // Get use statements from file
        $file = $contextRef->getFileName();

        if (! $file || ! file_exists($file)) {
            return null;
        }

        $content = file_get_contents($file);

        // Match: use Some\Namespace\XxxResource;
        if (preg_match('/use\s+([\w\\\\]+\\\\'.preg_quote($shortName, '/').')\s*;/', $content, $m)) {
            return $m[1];
        }

        // Try same namespace as context class
        $ns = $contextRef->getNamespaceName();

        if ($ns) {
            $fqcn = $ns.'\\'.$shortName;

            if (class_exists($fqcn)) {
                return $fqcn;
            }
        }

        return null;
    }

    /**
     * Check if class is a resource class (extends JsonResource).
     *
     * @param string $class Fully qualified class name
     * @return bool True if class is a resource
     */
    private function isResourceClass(string $class): bool
    {
        if (! class_exists($class)) {
            return false;
        }

        try {
            $ref = new ReflectionClass($class);

            // Check for JsonApiResource or Laravel's JsonResource
            while ($parent = $ref->getParentClass()) {
                $parentName = $parent->getName();

                if (str_contains($parentName, 'Resource')) {
                    return true;
                }

                $ref = $parent;
            }
        } catch (Throwable) {
        }

        return false;
    }

    /**
     * Get or create reflection class from cache.
     *
     * @param string $class Fully qualified class name
     * @return ReflectionClass Reflection class instance
     * @throws \ReflectionException
     */
    private function reflect(string $class): ReflectionClass
    {
        return $this->reflectionCache[$class]
            ??= new ReflectionClass($class);
    }

    /**
     * Get method source code with caching.
     *
     * @param ReflectionMethod $method Method reflection
     * @return string Method source code
     */
    private function getMethodSource(ReflectionMethod $method): string
    {
        $cacheKey = $method->getDeclaringClass()->getName().'::'.$method->getName();

        if (! isset($this->methodCache[$cacheKey])) {
            $file = $method->getFileName();

            if (! $file || ! file_exists($file)) {
                return '';
            }

            $lines = $this->getFileLines($file);
            $startLine = $method->getStartLine() - 1;
            $endLine = $method->getEndLine();
            $length = $endLine - $startLine;

            $this->methodCache[$cacheKey] = implode("\n", array_slice($lines, $startLine, $length));
        }

        return $this->methodCache[$cacheKey];
    }

    /**
     * Get file lines with caching.
     */
    /**
     * Get file lines with caching.
     *
     * @param string $file File path
     * @return array File lines array
     */
    private function getFileLines(string $file): array
    {
        if (! isset($this->fileCache[$file])) {
            if (! file_exists($file)) {
                return [];
            }

            $content = file_get_contents($file);
            $this->fileCache[$file] = $content ? explode("\n", $content) : [];
        }

        return $this->fileCache[$file];
    }
}
