<?php

namespace Jurager\Documentator\OpenApi;

use Faker\Generator;

class ExampleGenerator
{
    public function __construct(protected Generator $faker) {}

    public function reset(?int $seed): void
    {
        $this->faker->seed($seed);
    }

    public function generate(array $schema, array $components = [], int $items = 1): mixed
    {
        return $this->valueForSchema('', $schema, $components, $items);
    }

    public function populate(array $document, array $requestSchemas, ?int $seed): array
    {
        $this->reset($seed);
        $schemas = (array) $document['components']['schemas'];

        foreach ($requestSchemas as $name) {
            if (! array_key_exists('example', $schemas[$name])) {
                $schemas[$name]['example'] = $this->generate($schemas[$name], $schemas);
            }
        }

        foreach ($document['paths'] as $path => $operations) {
            foreach ($operations as $method => $operation) {
                $document['paths'][$path][$method]['responses'] = $this->populateResponses($operation['responses'], $schemas);
            }
        }

        $document['components']['schemas'] = $schemas ?: (object) [];

        return $document;
    }

    protected function populateResponses(array $responses, array $schemas): array
    {
        foreach ($responses as $status => $response) {
            foreach ($response['content'] ?? [] as $mediaType => $content) {
                if (isset($content['schema']) && ! array_key_exists('example', $content) && ! isset($content['examples'])) {
                    $responses[$status]['content'][$mediaType]['example'] = $this->generate($content['schema'], $schemas, 2);
                }
            }
        }

        return $responses;
    }

    protected function valueForSchema(string $name, array $schema, array $components, int $items, array $references = [], int $ordinal = 1): mixed
    {
        if (array_key_exists('example', $schema)) {
            return $schema['example'];
        }
        if (isset($schema['$ref'])) {
            $reference = $schema['$ref'];
            $key = str_replace('#/components/schemas/', '', $reference);
            if (in_array($reference, $references, true) || ! isset($components[$key])) {
                return null;
            }

            return $this->valueForSchema($name, $components[$key], $components, $items, [...$references, $reference], $ordinal);
        }
        if (! empty($schema['enum'])) {
            return $schema['enum'][0];
        }
        if (isset($schema['oneOf']) || isset($schema['anyOf'])) {
            return $this->valueForSchema($name, ($schema['oneOf'] ?? $schema['anyOf'])[0], $components, $items, $references, $ordinal);
        }

        $type = $schema['type'] ?? 'string';
        if ($type === 'object') {
            $value = [];
            foreach ($schema['properties'] ?? [] as $field => $property) {
                $value[$field] = $this->valueForSchema($field, $property, $components, $items, $references, $ordinal);
            }

            return (object) $value;
        }
        if ($type === 'array') {
            $count = min($schema['maxItems'] ?? PHP_INT_MAX, max($items, $schema['minItems'] ?? 0));
            $value = [];
            for ($index = 0; $index < $count; $index++) {
                $value[] = $this->valueForSchema($name, (array) ($schema['items'] ?? []), $components, $items, $references, $index + 1);
            }

            return $value;
        }

        $value = match ($schema['format'] ?? null) {
            'email' => $this->faker->safeEmail(),
            'uuid' => $this->faker->uuid(),
            'uri' => $this->faker->url(),
            'date' => $this->faker->date('Y-m-d', '2020-01-01'),
            'date-time' => $this->faker->date(DATE_ATOM, '2020-01-01'),
            'binary' => 'file',
            default => $name === 'id' && in_array($type, ['string', 'integer'], true)
                ? ($type === 'string' ? (string) $ordinal : $ordinal)
                : $this->generateValue($name, $type),
        };
        if (in_array($type, ['integer', 'number'], true)) {
            $minimum = $schema['minimum'] ?? -INF;
            $maximum = $schema['maximum'] ?? INF;
            if ($type === 'integer') {
                $minimum = ceil($minimum);
                $maximum = floor($maximum);
            }
            $value = min($maximum, max($minimum, $value));

            return $type === 'integer' ? (int) $value : $value;
        }
        if ($type === 'string') {
            $value .= str_repeat('x', max(0, ($schema['minLength'] ?? 0) - mb_strlen($value)));

            return mb_substr($value, 0, $schema['maxLength'] ?? mb_strlen($value));
        }

        return $value;
    }

    protected function generateValue(string $field, string $type): mixed
    {
        $field = strtolower($field);

        return match ($type) {
            'integer' => $this->generateInteger($field),
            'number' => $this->faker->randomFloat(2, 0, 1000),
            'boolean' => $this->generateBoolean($field),
            default => $this->generateString($field),
        };
    }

    protected function generateInteger(string $field): int
    {
        return match ($field) {
            'id', 'page' => 1,
            'per_page', 'limit' => 15,
            'total' => 100,
            'offset', 'skip' => 0,
            default => $this->faker->numberBetween(1, 100),
        };
    }

    protected function generateBoolean(string $field): bool
    {
        return ! preg_match('/(?:^|_)(disabled|hidden|deleted|blocked|banned|expired|archived)(?:_|$)/', $field);
    }

    protected function generateString(string $field): string
    {
        return match (true) {
            $field === 'id' => '1',
            str_contains($field, 'uuid') => $this->faker->uuid(),
            str_contains($field, 'email') => $this->faker->safeEmail(),
            str_contains($field, 'url'), str_contains($field, 'link'), str_contains($field, 'website') => $this->faker->url(),
            str_ends_with($field, '_at') => $this->faker->date(DATE_ATOM, '2020-01-01'),
            str_contains($field, 'date') => $this->faker->date('Y-m-d', '2020-01-01'),
            str_contains($field, 'name') => $this->faker->words(2, true),
            str_contains($field, 'description'), str_contains($field, 'summary') => $this->faker->sentence(),
            default => $this->faker->word(),
        };
    }
}
