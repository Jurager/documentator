<?php

namespace Jurager\Documentator\Builders;

use Illuminate\Routing\Route;
use Illuminate\Support\Str;
use Jurager\Documentator\Parsers\DocumentationParser;
use Jurager\Documentator\Formats\AbstractFormatInterface;

/**
 * Builds complete OpenAPI operations including parameters, request body, and responses.
 */
class OperationBuilder
{
    /**
     * @param  SchemaBuilder  $schemaBuilder  Schema builder for request/response schemas
     * @param  DocumentationParser  $docExtractor  Documentation and resource extractor
     * @param  AbstractFormatInterface  $format  Response format implementation
     * @param  array  $config  Configuration array
     */
    public function __construct(
        private SchemaBuilder $schemaBuilder,
        private DocumentationParser $docExtractor,
        private AbstractFormatInterface $format,
        private array $config = []
    ) {
    }

    /**
     * Generate OpenAPI operation for route and method.
     *
     * @param  Route  $route  Laravel route instance
     * @param  string  $method  HTTP method (get, post, put, patch, delete)
     * @param  array  $doc  Parsed documentation from PHPDoc
     * @param  array  $schemas  Reference to schemas array to append new schemas
     * @return array OpenAPI operation definition
     */
    public function generate(Route $route, string $method, array $doc, array &$schemas): array
    {
        $segments = $this->extractPathSegments($route);
        $resource = $doc['resource'] ?? Str::singular(Str::snake($segments ? end($segments) : 'resource'));
        $isCollection = $this->isCollectionEndpoint($route, $method);

        // Generate request body
        $requestBody = null;
        if (in_array($method, ['post', 'put', 'patch'])) {
            $result = $this->buildRequestBody($route, $method, $doc['validation'] ?? [], $doc, $segments);
            $requestBody = $result['body'];

            if ($result['schema']) {
                $schemas = array_merge($schemas, $result['schema']);
            }
        }

        // Extract response data from Resource class
        $responseData = $this->extractResponseData($route, $resource);

        // Build operation
        $operation = array_filter([
            'operationId' => $this->generateOperationId($route, $method, $segments),
            'summary' => $doc['summary'] ?? $this->generateSummary($method, $segments),
            'description' => $doc['description'] ?? null,
            'tags' => $doc['tags'] ?? $this->generateTags($segments),
            'deprecated' => $doc['deprecated'] ?? null,
            'parameters' => $this->buildParameters($route, $method, $doc) ?: null,
            'requestBody' => $requestBody,
            'responses' => $this->buildResponses($method, $resource, $doc, $responseData, $isCollection),
        ]);

        // Add default responses
        foreach ($this->config['responses']['default'] ?? [] as $status => $response) {
            $operation['responses'][$status] ??= $response;
        }

        // Handle authentication
        if (isset($doc['authenticated']) && $doc['authenticated'] === false) {
            $operation['security'] = [];
        }

        return $operation;
    }

    /**
     * Build OpenAPI parameters for a route.
     *
     * @param  Route  $route  Laravel route instance
     * @param  string  $method  HTTP method
     * @param  array  $doc  Parsed documentation
     * @return array OpenAPI parameters array
     */
    private function buildParameters(Route $route, string $method, array $doc): array
    {
        $params = [];

        // Path parameters
        foreach ($route->parameterNames() as $name) {
            $urlParam = collect($doc['urlParams'] ?? [])->firstWhere('name', $name);
            $params[] = [
                'name' => $name,
                'in' => 'path',
                'required' => $urlParam['required'] ?? true,
                'schema' => ['type' => $this->normalizeType($urlParam['type'] ?? 'string')],
                'description' => $urlParam['description'] ?? __('documentator::messages.id_of', ['name' => Str::headline($name)]),
            ];
        }

        // Query parameters (only for GET requests)
        if ($method === 'get') {
            foreach ($doc['queryParams'] ?? [] as $p) {
                // Convert dot notation to array notation: filter.name -> filter[name]
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
     * Generate request body specification.
     *
     * @param  Route  $route  Laravel route instance
     * @param  string  $method  HTTP method
     * @param  array  $rules  Validation rules
     * @param  array  $doc  Parsed documentation
     * @param  array  $segments  Path segments
     * @return array Request body specification with schema reference
     */
    private function buildRequestBody(Route $route, string $method, array $rules, array $doc, array $segments): array
    {
        $schema = $this->schemaBuilder->build($rules, $doc['bodyParams'] ?? []);

        // Remove path parameters from schema
        foreach ($route->parameterNames() as $param) {
            unset($schema['properties'][$param]);
            if (isset($schema['required'])) {
                $schema['required'] = array_values(array_diff($schema['required'], [$param]));
            }
        }

        if (empty($schema['properties'])) {
            return ['body' => null, 'schema' => null];
        }

        // Generate schema name
        $resource = $this->resolveResourceName($segments, $doc);
        $operationId = $this->generateOperationIdForSchema($route, $method, $segments);
        $schemaName = Str::studly("{$resource}_{$operationId}_Request");

        return [
            'body' => [
                'required' => true,
                'content' => [
                    'application/json' => [
                        'schema' => ['$ref' => "#/components/schemas/$schemaName"],
                    ],
                ],
            ],
            'schema' => [$schemaName => $schema],
        ];
    }

    /**
     * Generate OpenAPI responses for operation.
     */
    private function buildResponses(string $method, string $resource, array $doc, ?array $responseData, bool $isCollection): array
    {
        // If explicit responses defined in doc, use them
        if (! empty($doc['responses'])) {
            return $this->buildExplicitResponses($doc['responses']);
        }

        // Otherwise, use format-specific response generation
        return $this->format->operationResponse($method, $resource, $responseData, $isCollection);
    }

    /**
     * Build responses from explicit PHPDoc @response tags.
     */
    private function buildExplicitResponses(array $responses): array
    {
        $result = [];

        foreach ($responses as $r) {
            $status = (string) $r['status'];
            $result[$status] = [
                'description' => $this->getStatusDescription($r['status']),
                'content' => [
                    'application/json' => [
                        'schema' => is_array($r['content'])
                            ? ['example' => $r['content']]
                            : ['type' => 'string', 'example' => $r['content']],
                    ],
                ],
            ];
        }

        return $result;
    }

    /**
     * Get standard HTTP status description.
     */
    private function getStatusDescription(int $status): string
    {
        return match ($status) {
            200 => 'Success',
            201 => 'Created',
            204 => 'No Content',
            400 => 'Bad Request',
            401 => 'Unauthorized',
            403 => 'Forbidden',
            404 => 'Not Found',
            422 => 'Validation Error',
            500 => 'Server Error',
            default => "HTTP $status",
        };
    }

    /**
     * Extract response data from Resource class.
     */
    private function extractResponseData(Route $route, string $resource): ?array
    {
        $action = $route->getActionName();

        if ($action === 'Closure' || ! str_contains($action, '@')) {
            return null;
        }

        [$controllerClass, $controllerMethod] = explode('@', $action, 2);

        // Try to find resource class from controller
        $resourceClass = $this->docExtractor->findResourceClass($controllerClass, $controllerMethod);

        // Fallback: guess from resource name
        if (! $resourceClass) {
            $namespaces = $this->config['resources']['namespaces'] ?? ['App\\Http\\Resources', 'App\\Models'];
            $resourceClass = $this->docExtractor->guessResourceClass($resource, $namespaces);
        }

        if (! $resourceClass) {
            return null;
        }

        return $this->docExtractor->parseResource($resourceClass);
    }

    /**
     * Extract path segments from route.
     */
    private function extractPathSegments(Route $route): array
    {
        return array_values(array_filter(
            explode('/', $route->uri()),
            fn ($s) => ! str_starts_with($s, '{')
        ));
    }

    /**
     * Check if endpoint returns a collection.
     */
    private function isCollectionEndpoint(Route $route, string $method): bool
    {
        if ($method !== 'get') {
            return false;
        }

        $uriSegments = explode('/', trim($route->uri(), '/'));
        $lastSegment = $uriSegments ? end($uriSegments) : '';

        return ! str_starts_with($lastSegment, '{');
    }

    /**
     * Generate operation ID.
     */
    private function generateOperationId(Route $route, string $method, array $segments): string
    {
        if ($name = $route->getName()) {
            return str_replace('.', '_', $name);
        }

        return strtolower($method).'_'.Str::snake(implode('_', $segments) ?: 'root');
    }

    /**
     * Generate operation ID for schema naming.
     */
    private function generateOperationIdForSchema(Route $route, string $method, array $segments): string
    {
        $parts = array_filter($segments, fn ($s) => ! str_starts_with($s, '{'));

        return Str::camel($method.'_'.implode('_', $parts));
    }

    /**
     * Generate summary from method and segments.
     */
    private function generateSummary(string $method, array $segments): string
    {
        $resource = $segments ? Str::headline(end($segments)) : 'Resource';

        return match ($method) {
            'get' => "Get $resource",
            'post' => "Create $resource",
            'put', 'patch' => "Update $resource",
            'delete' => "Delete $resource",
            default => Str::headline($method)." $resource",
        };
    }

    /**
     * Generate tags from path segments.
     */
    private function generateTags(array $segments): array
    {
        return $segments ? [Str::headline(end($segments))] : ['General'];
    }

    /**
     * Resolve resource name from segments or doc.
     */
    private function resolveResourceName(array $segments, array $doc): string
    {
        if (isset($doc['resource'])) {
            return $doc['resource'];
        }

        $lastSegment = end($segments) ?: 'resource';

        return Str::singular(Str::snake($lastSegment));
    }

    /**
     * Normalize type name to OpenAPI type.
     */
    private function normalizeType(string $type): string
    {
        return match (strtolower($type)) {
            'int', 'integer', 'numeric' => 'integer',
            'float', 'double', 'number' => 'number',
            'bool', 'boolean' => 'boolean',
            default => 'string',
        };
    }
}
