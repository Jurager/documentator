<?php

namespace Jurager\Documentator\OpenApi;

use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Exists;
use Illuminate\Validation\Rules\In;
use Illuminate\Validation\Rules\Unique;

class SchemaBuilder
{
    public function __construct(protected TypeResolver $types) {}

    public function build(array $rules, array $parameters = []): array
    {
        $schema = ['type' => 'object'];

        foreach ($rules as $field => $ruleset) {
            $tokens = match (true) {
                is_array($ruleset) => $ruleset,
                is_string($ruleset) => explode('|', $ruleset),
                default => [],
            };
            $required = in_array('required', $tokens, true) && ! in_array('sometimes', $tokens, true);
            $segments = explode('.', $field);
            $name = $segments[array_key_last($segments)];
            $leaf = $this->fieldSchema($name === '*' ? ($segments[count($segments) - 2] ?? 'item') : $name, $tokens);
            $schema = $this->setField($schema, $segments, $leaf, $required);
        }

        foreach ($parameters as $parameter) {
            $leaf = $this->types->schema($parameter['type']);

            if (($parameter['description'] ?? '') !== '') {
                $leaf['description'] = $parameter['description'];
            }

            if (array_key_exists('example', $parameter)) {
                $leaf['example'] = $parameter['example'];
            }

            $segments = explode('.', str_replace('[]', '.*', $parameter['name']));
            $schema = $this->setField($schema, $segments, $leaf, $parameter['required'] ?? false);
        }

        return $schema;
    }

    protected function setField(array $schema, array $segments, array $fieldSchema, bool $required): array
    {
        $field = array_shift($segments);
        $isItem = $field === '*';
        $schema['type'] = $isItem ? 'array' : 'object';
        $child = $isItem ? (array) ($schema['items'] ?? []) : ($schema['properties'][$field] ?? []);

        if ($segments !== []) {
            $child = $this->setField($child, $segments, $fieldSchema, $required);
        } else {
            // An unconstrained array must not replace already inferred item fields.
            if (isset($child['items']) && ($fieldSchema['items'] ?? null) instanceof \stdClass) {
                unset($fieldSchema['items']);
            }

            $child = array_replace_recursive($child, $fieldSchema);
        }

        if ($isItem) {
            $schema['items'] = $child;

            return $schema;
        }

        $schema['properties'][$field] = $child;
        if ($required && $segments === []) {
            $schema['required'] = array_values(array_unique([...($schema['required'] ?? []), $field]));
        }

        return $schema;
    }

    protected function fieldSchema(string $field, array $rules): array
    {
        $type = $this->types->fromRules($rules);
        $schema = ['type' => $type, 'description' => $this->description($field, $rules)];

        foreach ($rules as $rule) {
            if ($rule instanceof In) {
                $rule = (string) $rule;
            }

            if (! is_string($rule)) {
                continue;
            }

            [$name, $value] = array_pad(explode(':', $rule, 2), 2, null);

            if (in_array($name, ['min', 'max'], true) && is_numeric($value)) {
                // Laravel measures file sizes in kilobytes, not string lengths.
                if (in_array('file', $rules, true) || in_array('image', $rules, true)) {
                    continue;
                }

                $key = match ($type) {
                    'string' => $name === 'min' ? 'minLength' : 'maxLength',
                    'array' => $name === 'min' ? 'minItems' : 'maxItems',
                    'integer', 'number' => $name === 'min' ? 'minimum' : 'maximum',
                    default => null,
                };

                if ($key !== null) {
                    $schema[$key] = in_array($type, ['integer', 'number'], true)
                        ? $value + 0
                        : (int) ($name === 'min' ? ceil((float) $value) : floor((float) $value));
                }

                continue;
            }

            if ($name === 'in' && $value !== null) {
                $values = str_getcsv($value, ',', '"', '');
                $schema['enum'] = array_map(fn ($item) => match ($type) {
                    'integer' => (int) $item,
                    'number' => (float) $item,
                    'boolean' => (bool) $item,
                    default => $item,
                }, $values);
            }

            $format = match ($name) {
                'email' => 'email',
                'url' => 'uri',
                'uuid' => 'uuid',
                'date' => 'date',
                'file', 'image' => 'binary',
                default => null,
            };

            if ($format !== null) {
                $schema['format'] = $format;
            }

            if ($name === 'nullable') {
                $schema['nullable'] = true;
            }
        }

        if ($type === 'array') {
            $schema['items'] = (object) [];
        }

        return $schema;
    }

    protected function description(string $field, array $rules): string
    {
        $descriptions = [];

        foreach ($rules as $rule) {
            $description = match (true) {
                $rule instanceof Unique => __('documentator::documentator.unique'),
                $rule instanceof Exists => __('documentator::documentator.exists'),
                is_string($rule) && str_starts_with($rule, 'unique:') => __('documentator::documentator.unique'),
                is_string($rule) && str_starts_with($rule, 'exists:') => __('documentator::documentator.exists'),
                is_string($rule) && str_starts_with($rule, 'max:') => __('documentator::documentator.max', ['value' => substr($rule, 4)]),
                is_string($rule) && str_starts_with($rule, 'min:') => __('documentator::documentator.min', ['value' => substr($rule, 4)]),
                $rule === 'email' => 'email',
                default => null,
            };

            if ($description !== null) {
                $descriptions[] = $description;
            }
        }

        return Str::headline($field).($descriptions ? ' ('.implode(', ', $descriptions).')' : '');
    }
}
