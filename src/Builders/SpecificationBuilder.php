<?php

namespace Jurager\Documentator\Builders;

use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Jurager\Documentator\Collectors\RouteCollector;
use Jurager\Documentator\Formats\AbstractFormatInterface;
use Jurager\Documentator\Parsers\DocumentationParser;
use Jurager\Documentator\Resolvers\FieldTypeResolver;

class SpecificationBuilder
{
    private array $schemas = [];

    private array $usedTags = [];

    private ?\Closure $progressCallback = null;

    private DocumentationParser $docExtractor;

    private OperationBuilder $operationBuilder;

    private AbstractFormatInterface $format;

    public function __construct(
        private RouteCollector $routeCollector,
        private array $config
    ) {
        $this->initializeDependencies();
    }

    /**
     * Initialize all dependencies.
     */
    private function initializeDependencies(): void
    {
        $typeResolver = new FieldTypeResolver();
        $this->docExtractor = new DocumentationParser($typeResolver);

        $schemaBuilder = new SchemaBuilder($this->config['type_map'] ?? []);
        $this->format = $this->resolveFormat($schemaBuilder);

        $this->operationBuilder = new OperationBuilder(
            $schemaBuilder,
            $this->docExtractor,
            $this->format,
            $this->config
        );
    }

    /**
     * Resolve response format from config.
     */
    private function resolveFormat(SchemaBuilder $schemaBuilder): AbstractFormatInterface
    {
        $format = $this->config['format'];

        $formats = array_merge([
            'simple' => \Jurager\Documentator\Formats\SimpleFormat::class,
            'json-api' => \Jurager\Documentator\Formats\JsonApiFormat::class,
        ], $this->config['custom_formats'] ?? []);

        if (class_exists($format)) {
            return new $format($schemaBuilder);
        }

        if (! isset($formats[$format])) {
            throw new \InvalidArgumentException(
                "Unknown format: $format. Available: ".implode(', ', array_keys($formats))
            );
        }

        return new $formats[$format]($schemaBuilder);
    }

    /**
     * Set progress callback for route processing.
     */
    public function setProgressCallback(?\Closure $callback): void
    {
        $this->progressCallback = $callback;
    }

    /**
     * Build complete OpenAPI specification.
     */
    public function build(): array
    {
        $paths = $this->buildPaths();

        $spec = [
            'openapi' => $this->config['openapi_version'] ?? '3.0.3',
            'info' => $this->buildInfo(),
            'servers' => $this->buildServers(),
            'paths' => $paths ?: new \stdClass(),
            'components' => [
                'schemas' => array_merge($this->format->schemas(), $this->schemas),
                'responses' => $this->format->responses(),
                'securitySchemes' => $this->config['security']['schemes'] ?? [],
            ],
        ];

        if ($tags = $this->buildTags()) {
            $spec['tags'] = $tags;
        }

        if ($defaultSecurity = $this->config['security']['default'] ?? null) {
            $spec['security'] = is_array($defaultSecurity) ? [$defaultSecurity] : $defaultSecurity;
        }

        return $spec;
    }

    /**
     * Build info section.
     */
    private function buildInfo(): array
    {
        $info = $this->config['info'] ?? [];

        return array_filter([
            'title' => $info['title'] ?? config('app.name', 'API'),
            'description' => $info['description'] ?? null,
            'version' => $info['version'] ?? '1.0.0',
            'contact' => array_filter($info['contact'] ?? []),
            'license' => array_filter($info['license'] ?? []),
        ]);
    }

    /**
     * Build servers section.
     */
    private function buildServers(): array
    {
        $servers = $this->config['servers'] ?? [['url' => config('app.url', 'http://localhost')]];

        return array_map(function ($s) {
            $server = array_filter([
                'url' => rtrim($s['url'], '/'),
                'description' => $s['description'] ?? null,
            ]);

            if (! empty($s['variables'])) {
                $server['variables'] = $this->buildServerVariables($s['variables']);
            }

            return $server;
        }, $servers);
    }

    /**
     * Build server variables section.
     */
    private function buildServerVariables(array $variables): array
    {
        $result = [];

        foreach ($variables as $name => $variable) {
            $result[$name] = array_filter([
                'default' => $variable['default'] ?? '',
                'description' => $variable['description'] ?? null,
                'enum' => $variable['enum'] ?? null,
            ], fn ($v) => $v !== null);
        }

        return $result;
    }

    /**
     * Build paths (operations) from routes.
     */
    private function buildPaths(): array
    {
        $routes = $this->routeCollector->collect($this->config['routes'] ?? []);

        if ($routes->isEmpty()) {
            return [];
        }

        $paths = $this->processPaths($routes);

        ksort($paths);

        return $paths;
    }

    /**
     * Process routes into paths.
     */
    private function processPaths(Collection $routes): array
    {
        $paths = [];
        $allowedMethods = $this->config['routes']['methods'] ?? ['GET', 'POST', 'PUT', 'PATCH', 'DELETE'];
        $allowedMethods = array_map('strtolower', $allowedMethods);

        foreach ($routes as $route) {
            $routeMethods = $this->routeCollector->getAllowedMethods($route, $allowedMethods);

            if (empty($routeMethods)) {
                continue;
            }

            $path = $this->routeCollector->normalizePath($route);
            $doc = $this->extractDocumentation($route);

            // Call progress callback if set
            if ($this->progressCallback) {
                ($this->progressCallback)($route, $path, $routeMethods);
            }

            // Extract validation rules and add to doc
            $doc['validation'] = $this->docExtractor->extractValidation($route);

            // Extract tags
            $tags = $this->extractTags($route, $doc);
            foreach ($tags as $tag) {
                $this->usedTags[$tag] = true;
            }
            $doc['tags'] = $tags;

            foreach ($routeMethods as $method) {
                $paths[$path][$method] = $this->operationBuilder->generate(
                    $route,
                    $method,
                    $doc,
                    $this->schemas
                );
            }
        }

        return $paths;
    }

    /**
     * Extract documentation from route.
     */
    private function extractDocumentation($route): array
    {
        try {
            $action = $route->getActionName();

            if ($action === 'Closure' || ! str_contains($action, '@')) {
                return [];
            }

            [$class, $method] = explode('@', $action, 2);

            if (! class_exists($class) || ! method_exists($class, $method)) {
                return [];
            }

            $reflection = new \ReflectionMethod($class, $method);
            $docComment = $reflection->getDocComment();

            return $docComment ? $this->docExtractor->parseDocComment($docComment) : [];
        } catch (\Throwable) {
            return [];
        }
    }

    /**
     * Extract tags from route.
     */
    private function extractTags($route, array $doc): array
    {
        if (! empty($doc['group'])) {
            return [$doc['group']];
        }

        if (! empty($doc['tags'])) {
            return (array) $doc['tags'];
        }

        // Try to extract from route name first (e.g., "attributes.index" -> "Attributes")
        if ($name = $route->getName()) {
            $parts = explode('.', $name);
            if (count($parts) > 1) {
                // Use the first part of the route name (resource name)
                return [Str::headline($parts[0])];
            }
        }

        // Fallback to URI segments
        $segments = array_values(array_filter(
            explode('/', $route->uri()),
            fn ($s) => $s !== '' && ! str_starts_with($s, '{')
        ));

        if (empty($segments)) {
            return ['General'];
        }

        // Use the last non-parameter segment, with proper UTF-8 support
        return [Str::headline(end($segments))];
    }

    /**
     * Build tags section.
     */
    private function buildTags(): array
    {
        $tagsConfig = $this->config['tags'] ?? [];
        $configured = $tagsConfig['definitions'] ?? [];
        $autoGenerate = $tagsConfig['auto_generate'] ?? true;
        $sort = $tagsConfig['sort'] ?? true;
        $tags = [];

        foreach ($configured as $name => $description) {
            $tags[$name] = ['name' => $name, 'description' => $description];
        }

        if ($autoGenerate) {
            foreach ($this->usedTags as $name => $_) {
                $tags[$name] ??= ['name' => $name];
            }
        }

        $result = array_values($tags);

        if ($sort) {
            usort($result, fn ($a, $b) => $a['name'] <=> $b['name']);
        }

        return $result;
    }

    /**
     * Get number of processed routes.
     */
    public function getProcessedCount(): int
    {
        return $this->routeCollector->collect($this->config['routes'] ?? [])->count();
    }
}
