<?php

namespace Jurager\Documentator\OpenApi;

use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Support\Str;
use InvalidArgumentException;
use JsonSerializable;

class Document implements Arrayable, JsonSerializable
{
    protected array $paths = [];

    protected array $operationIds = [];

    protected array $requestSchemas = [];

    public function __construct(protected array $attributes)
    {
        $this->attributes['components']['schemas'] = (array) ($attributes['components']['schemas'] ?? []);
    }

    public function addOperation(string $path, string $method, array $operation): void
    {
        if (isset($this->paths[$path][$method])) {
            throw new InvalidArgumentException("Multiple routes describe {$method} {$path}. Exclude one route before generating the specification.");
        }

        $id = $this->uniqueOperationId($operation['operationId'], $method, $path);
        $operation['operationId'] = $id;

        foreach ($operation['requestBody']['content'] ?? [] as $mediaType => $content) {
            $name = $this->addRequestSchema($id, $method, $path, $mediaType, $content['schema']);
            $operation['requestBody']['content'][$mediaType]['schema'] = ['$ref' => "#/components/schemas/{$name}"];
        }

        $this->paths[$path][$method] = $operation;
    }

    public function tags(): array
    {
        $tags = [];

        foreach ($this->paths as $operations) {
            foreach ($operations as $operation) {
                $tags = [...$tags, ...($operation['tags'] ?? [])];
            }
        }

        return array_values(array_unique($tags));
    }

    public function requestSchemas(): array
    {
        return $this->requestSchemas;
    }

    public function toArray(): array
    {
        $paths = $this->paths;
        ksort($paths);

        $attributes = $this->attributes;
        $attributes['components']['schemas'] = $attributes['components']['schemas'] ?: (object) [];

        return array_replace($attributes, ['paths' => $paths ?: (object) []]);
    }

    public function jsonSerialize(): array
    {
        return $this->toArray();
    }

    protected function uniqueOperationId(string $id, string $method, string $path): string
    {
        $candidate = $id;
        $suffix = substr(sha1("{$method} {$path}"), 0, 10);
        $index = 0;

        while (isset($this->operationIds[$candidate])) {
            $candidate = $id.'_'.$suffix.($index > 0 ? '_'.$index : '');
            $index++;
        }

        $this->operationIds[$candidate] = true;

        return $candidate;
    }

    protected function addRequestSchema(string $id, string $method, string $path, string $mediaType, array $schema): string
    {
        // A custom format owns its component names; generated requests must not overwrite them.
        $name = preg_replace('/[^a-zA-Z0-9._-]/', '_', Str::studly($id)).'Request_'.substr(sha1("{$method} {$path} {$mediaType}"), 0, 8);
        $candidate = $name;
        $index = 1;

        while (isset($this->attributes['components']['schemas'][$candidate])) {
            $candidate = $name.'_'.$index++;
        }

        $this->attributes['components']['schemas'][$candidate] = $schema;
        $this->requestSchemas[] = $candidate;

        return $candidate;
    }
}
