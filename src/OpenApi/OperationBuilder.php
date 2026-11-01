<?php

namespace Jurager\Documentator\OpenApi;

use Illuminate\Contracts\Config\Repository;
use Illuminate\Routing\Route;
use Illuminate\Support\Str;
use Jurager\Documentator\Contracts\ResponseFormat;
use Jurager\Documentator\Routing\RouteInformation;

class OperationBuilder
{
    public function __construct(
        protected SchemaBuilder $schemas,
        protected ParameterBuilder $parameters,
        protected Repository $config,
    ) {}

    public function build(RouteInformation $information, string $method, ResponseFormat $format): array
    {
        $route = $information->route;
        $doc = $information->doc;
        $operation = [
            'operationId' => $this->operationId($route, $method),
            'summary' => $doc['summary'] ?? $this->summary($method, $information->segments()),
            'tags' => $information->tags(),
            'responses' => $this->responses($information, $method, $format),
        ];

        foreach (['description', 'deprecated'] as $key) {
            if (array_key_exists($key, $doc)) {
                $operation[$key] = $doc[$key];
            }
        }

        $parameters = $this->parameters->build($information, $method, $this->config->get('documentator.examples.enabled') ?? true);
        if ($parameters !== []) {
            $operation['parameters'] = $parameters;
        }

        $body = $this->requestBody($information, $method);
        if ($body !== []) {
            $operation['requestBody'] = $body;
        }

        if (($doc['authenticated'] ?? null) === false) {
            $operation['security'] = [];
        }

        return $operation;
    }

    protected function requestBody(RouteInformation $information, string $method): array
    {
        if (! in_array($method, ['post', 'put', 'patch', 'delete'], true)) {
            return [];
        }

        $schema = $this->schemas->build($information->doc['validation'] ?? [], $information->doc['bodyParams'] ?? []);
        foreach ($information->route->parameterNames() as $parameter) {
            unset($schema['properties'][$parameter]);
            if (isset($schema['required'])) {
                $schema['required'] = array_values(array_diff($schema['required'], [$parameter]));
            }
        }
        if (empty($schema['required'])) {
            unset($schema['required']);
        }
        if (empty($schema['properties'])) {
            return [];
        }

        $mediaType = $this->hasBinary($schema) ? 'multipart/form-data' : 'application/json';

        return [
            'required' => ! empty($schema['required']),
            'content' => [$mediaType => ['schema' => $schema]],
        ];
    }

    protected function operationId(Route $route, string $method): string
    {
        $name = $route->getName();
        if ($name !== null) {
            $id = str_replace('.', '_', $name);

            return count(array_diff($route->methods(), ['HEAD'])) > 1 ? "{$id}_{$method}" : $id;
        }

        $path = trim(preg_replace('/[{}?]/', '', $route->uri()), '/');

        return "{$method}_".Str::snake(str_replace('/', '_', $path) ?: 'root');
    }

    protected function summary(string $method, array $segments): string
    {
        $resource = Str::headline(end($segments) ?: 'Resource');

        return match ($method) {
            'get' => "Get {$resource}",
            'post' => "Create {$resource}",
            'put', 'patch' => "Update {$resource}",
            'delete' => "Delete {$resource}",
            default => Str::headline($method)." {$resource}",
        };
    }

    protected function hasBinary(array $schema): bool
    {
        if (($schema['format'] ?? null) === 'binary') {
            return true;
        }
        if (isset($schema['items']) && $this->hasBinary((array) $schema['items'])) {
            return true;
        }
        foreach ($schema['properties'] ?? [] as $property) {
            if ($this->hasBinary($property)) {
                return true;
            }
        }

        return false;
    }

    protected function responses(RouteInformation $information, string $method, ResponseFormat $format): array
    {
        $responses = [];
        foreach ($information->doc['responses'] ?? [] as $response) {
            $status = $response['status'];
            $responses[$status] = ['description' => $this->description($status)];
            if (! in_array($status, [204, 304], true)) {
                $responses[$status]['content'] = ['application/json' => ['example' => $response['content']]];
            }
        }

        if ($responses === []) {
            $status = match ($method) {
                'post' => 201,
                'delete' => 204,
                default => 200,
            };
            $responses[$status] = ['description' => $status === 204 ? 'No Content' : $this->description($status)];
            if ($status !== 204) {
                $responses[$status] = array_replace($responses[$status], $format->response(
                    $information->resource, $information->data, $information->isCollection($method),
                ));
            }
        }

        foreach ($this->config->get('documentator.responses.default') ?? [] as $status => $response) {
            $responses[$status] ??= $response;
        }

        return $responses;
    }

    protected function description(int $status): string
    {
        return $this->config->get("documentator.responses.descriptions.{$status}") ?? match ($status) {
            200 => 'Success',
            201 => 'Created',
            204 => 'No Content',
            304 => 'Not Modified',
            400 => 'Bad Request',
            401 => 'Unauthorized',
            403 => 'Forbidden',
            404 => 'Not Found',
            422 => 'Validation Error',
            500 => 'Server Error',
            default => "HTTP {$status}",
        };
    }
}
