<?php

namespace Jurager\Documentator\Builders;

use Illuminate\Support\Str;
use Jurager\Documentator\Generators\ExampleGenerator;
use Jurager\Documentator\Support\RuleNormalizer;

/**
 * Строит OpenAPI-схему из правил валидации; примеры делегирует ExampleGenerator.
 */
class SchemaBuilder
{
    private ExampleGenerator $examples;

    /**
     * @param  array  $typeMap  Custom type mapping for validation rules
     * @param  ExampleGenerator|null  $examples  Генератор примеров
     */
    public function __construct(
        private array $typeMap = [],
        ?ExampleGenerator $examples = null
    ) {
        $this->examples = $examples ?? new ExampleGenerator();
    }

    /**
     * Build schema from validation rules and doc params.
     *
     * @param  array  $rules  Validation rules array
     * @param  array  $docParams  Documentation parameters from PHPDoc
     * @return array OpenAPI schema definition
     */
    public function build(array $rules, array $docParams = []): array
    {
        $props = [];
        $required = [];

        foreach ($rules as $field => $r) {
            $ruleList = RuleNormalizer::tokens($r);
            $isRequired = in_array('required', $ruleList, true);

            $segments = explode('.', $field);
            $leafName = end($segments);

            // Top-level scalar
            if (count($segments) === 1) {
                if ($isRequired) {
                    $required[] = $field;
                }
                $props[$field] = $this->buildFieldSchema($field, $ruleList);

                continue;
            }

            // Nested / array path
            $leafSchema = $this->buildFieldSchema($leafName === '*' ? $segments[count($segments) - 2] : $leafName, $ruleList);
            $this->setNestedSchema($props, $segments, $leafSchema, $isRequired);
        }

        // Merge body params from doc comments: override description, fill missing fields
        foreach ($docParams as $p) {
            $isFileType = strtolower(rtrim($p['type'], '[]')) === 'file';

            // Support Scribe-style array notation in param names (e.g. "attributes[].id"),
            // converting "[]" markers into the "*" segment understood by setNestedSchema().
            $segments = $this->parseParamSegments($p['name']);

            if (count($segments) === 1) {
                $name = $segments[0];
                $docType = $this->normalizeType($p['type']);

                if (isset($props[$name])) {
                    // Field already built from FormRequest rules — override description and type from docParam
                    if ($p['description'] !== '') {
                        $props[$name]['description'] = $p['description'];
                    }
                    if ($docType !== 'string') {
                        $props[$name]['type'] = $docType;
                    }
                } else {
                    $props[$name] = [
                        'type' => $docType,
                        'description' => $p['description'],
                    ];
                }

                if ($isFileType) {
                    $props[$name]['format'] = 'binary';
                }

                if (($p['example'] ?? null) !== null) {
                    $props[$name]['example'] = $p['example'];
                }

                // Scalar array (e.g. "integer[]"): describe the item type so the example
                // renders as an array of that scalar instead of a single string. Object
                // arrays ("object[]") are left for their "[].field" params to populate.
                if ($docType === 'array' && str_ends_with($p['type'], '[]')) {
                    $base = $this->normalizeType(rtrim($p['type'], '[]'));
                    if ($base !== 'object' && ! isset($props[$name]['items'])) {
                        $props[$name]['items'] = ['type' => $base];
                    }
                }

                if (($p['required'] ?? false) && ! in_array($name, $required)) {
                    $required[] = $name;
                }

                continue;
            }

            $leafSchema = ['type' => $this->normalizeType($p['type'])];
            if ($isFileType) {
                $leafSchema['format'] = 'binary';
            }
            if (! empty($p['description'])) {
                $leafSchema['description'] = $p['description'];
            }
            if (($p['example'] ?? null) !== null) {
                $leafSchema['example'] = $p['example'];
            }

            $this->setNestedSchema($props, $segments, $leafSchema, (bool) ($p['required'] ?? false));
        }

        $example = $this->examples->buildExample($props);

        return array_filter([
            'type' => 'object',
            'properties' => $props,
            'required' => $required ?: null,
            'example' => $example ?: null,
        ]);
    }

    /**
     * Split a doc param name into path segments, expanding Scribe-style array
     * markers ("[]") into the "*" segment used by setNestedSchema().
     *
     * Examples:
     *   'group_id'              → ['group_id']
     *   'attributes[]'          → ['attributes', '*']
     *   'attributes[].id'       → ['attributes', '*', 'id']
     *   'rows[].cells[].value'  → ['rows', '*', 'cells', '*', 'value']
     */
    private function parseParamSegments(string $name): array
    {
        $segments = [];

        foreach (explode('.', $name) as $part) {
            $stars = 0;
            while (str_ends_with($part, '[]')) {
                $part = substr($part, 0, -2);
                $stars++;
            }

            if ($part !== '') {
                $segments[] = $part;
            }

            for ($i = 0; $i < $stars; $i++) {
                $segments[] = '*';
            }
        }

        return $segments;
    }

    /**
     * Place a leaf schema into a (possibly multi-level) nested array path.
     *
     * Segments may include any number of `*` markers which denote array items.
     * Examples:
     *   ['regions']                                          → scalar prop
     *   ['regions', '*']                                     → array of scalars
     *   ['region_warehouses', '*', 'region_id']              → array of objects with prop
     *   ['region_warehouses', '*', 'warehouses', '*', 'id']  → array of objects → array of objects with prop
     */
    private function setNestedSchema(array &$props, array $segments, array $leafSchema, bool $required = false): void
    {
        $field = $segments[0];
        $rest = array_slice($segments, 1);

        if (count($rest) === 0) {
            $props[$field] = $leafSchema;

            return;
        }

        if ($rest[0] === '*') {
            $props[$field] ??= [
                'type' => 'array',
                'description' => Str::headline($field),
            ];
            // Ensure declared as array even if previously inferred otherwise
            $props[$field]['type'] = 'array';

            $afterStar = array_slice($rest, 1);

            if (count($afterStar) === 0) {
                // Scalar array — leaf schema describes the item itself
                $itemSchema = $leafSchema;
                unset($itemSchema['description']);
                $props[$field]['items'] = $itemSchema;

                return;
            }

            // Object items
            if (! isset($props[$field]['items']) || ! isset($props[$field]['items']['properties'])) {
                $props[$field]['items'] = ['type' => 'object', 'properties' => [], 'required' => []];
            }
            $this->setNestedSchema($props[$field]['items']['properties'], $afterStar, $leafSchema, $required);

            if ($required && count($afterStar) === 1) {
                $props[$field]['items']['required'] ??= [];
                if (! in_array($afterStar[0], $props[$field]['items']['required'], true)) {
                    $props[$field]['items']['required'][] = $afterStar[0];
                }
            }

            return;
        }

        // Plain nested object property
        if (! isset($props[$field]) || ($props[$field]['type'] ?? null) !== 'object' || ! isset($props[$field]['properties']) || ! is_array($props[$field]['properties'])) {
            $props[$field] = [
                'type' => 'object',
                'description' => $props[$field]['description'] ?? Str::headline($field),
                'properties' => [],
                'required' => [],
            ];
        }
        $this->setNestedSchema($props[$field]['properties'], $rest, $leafSchema, $required);
    }

    /**
     * Build schema for a single field from validation rules.
     */
    private function buildFieldSchema(string $field, array $rules): array
    {
        $type = $this->resolveType($rules);

        $schema = [
            'type' => $type,
            'description' => $this->buildDescription($field, $rules),
        ];

        foreach ($rules as $rule) {

            if (!is_string($rule)) {
                continue; // игнорируем Closure и объекты Rule
            }

            match (true) {
                str_starts_with($rule, 'max:') =>
                $schema[$type === 'string' ? 'maxLength' : 'maximum'] = (int) substr($rule, 4),

                str_starts_with($rule, 'min:') =>
                $schema[$type === 'string' ? 'minLength' : 'minimum'] = (int) substr($rule, 4),

                str_starts_with($rule, 'in:') =>
                $schema['enum'] = explode(',', substr($rule, 3)),

                $rule === 'email' =>
                $schema['format'] = 'email',

                $rule === 'url' =>
                $schema['format'] = 'uri',

                $rule === 'uuid' =>
                $schema['format'] = 'uuid',

                $rule === 'date' =>
                $schema['format'] = 'date',

                $rule === 'file', $rule === 'image' =>
                $schema['format'] = 'binary',

                $rule === 'nullable' =>
                $schema['nullable'] = true,

                default => null,
            };
        }

        return $schema;
    }

    /**
     * Resolve OpenAPI type from validation rules.
     */
    private function resolveType(array $rules): string
    {
        foreach ($rules as $rule) {
            if (! is_string($rule)) {
                continue; // skip Closure and Rule objects
            }

            $name = explode(':', $rule)[0];
            if (isset($this->typeMap[$name])) {
                return $this->typeMap[$name];
            }
        }

        return 'string';
    }

    /**
     * Build field description from validation rules.
     */
    private function buildDescription(string $field, array $rules): string
    {
        $desc = [];

        foreach ($rules as $rule) {

            // Строковые правила
            if (is_string($rule)) {
                match (true) {
                    str_starts_with($rule, 'max:') =>
                    $desc[] = __('documentator::documentator.max', ['value' => substr($rule, 4)]),

                    str_starts_with($rule, 'min:') =>
                    $desc[] = __('documentator::documentator.min', ['value' => substr($rule, 4)]),

                    $rule === 'email' =>
                    $desc[] = 'email',

                    str_starts_with($rule, 'unique') =>
                    $desc[] = __('documentator::documentator.unique'),

                    str_starts_with($rule, 'exists:') =>
                    $desc[] = __('documentator::documentator.exists'),

                    default => null,
                };

                continue;
            }

            // Объектные правила
            if ($rule instanceof Unique) {
                $desc[] = __('documentator::documentator.unique');
                continue;
            }

            if ($rule instanceof Exists) {
                $desc[] = __('documentator::documentator.exists');
                continue;
            }
        }

        return $desc
            ? Str::headline($field).' ('.implode(', ', $desc).')'
            : Str::headline($field);
    }

    /**
     * Normalize type name.
     */
    private function normalizeType(string $type): string
    {
        if (str_ends_with($type, '[]')) {
            return 'array';
        }

        $base = strtolower($type);

        return match ($base) {
            'int', 'integer', 'numeric' => 'integer',
            'float', 'double', 'number' => 'number',
            'bool', 'boolean' => 'boolean',
            'array' => 'array',
            'object' => 'object',
            default => 'string',
        };
    }
}
