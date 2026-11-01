<?php

namespace Jurager\Documentator\OpenApi;

use BackedEnum;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Support\Str;
use InvalidArgumentException;

class TypeResolver
{
    protected array $typeMap;

    public function __construct(Repository $config)
    {
        $types = $config->get('documentator.type_map') ?? [];
        if (! is_array($types)) {
            throw new InvalidArgumentException('The type_map setting must be an array.');
        }

        foreach ($types as $rule => $type) {
            if (! is_string($rule) || ! in_array($type, ['string', 'integer', 'number', 'boolean', 'array', 'object'], true)) {
                throw new InvalidArgumentException('Each type_map entry must map a rule name to an OpenAPI type.');
            }
        }

        $this->typeMap = $types;
    }

    /**
     * Resolve type from field name.
     */
    public function fromFieldName(string $name): string
    {
        $name = Str::snake($name);

        return match (true) {
            str_starts_with($name, 'is_'), str_starts_with($name, 'has_'), str_starts_with($name, 'can_') => 'boolean',
            // ID fields
            str_ends_with($name, '_id'), $name === 'id' => 'integer',

            // Timestamps
            str_ends_with($name, '_at') => 'string', // ISO 8601 datetime

            // Counts and quantities
            str_ends_with($name, '_count') => 'integer',
            (bool) preg_match('/(?:^|_)(count|quantity|stock)(?:_|$)/', $name) => 'integer',

            // Numeric fields
            str_contains($name, 'price'), str_contains($name, 'amount'), str_contains($name, 'cost') => 'number',
            str_contains($name, 'total'), str_contains($name, 'sum'), str_contains($name, 'balance') => 'number',
            str_contains($name, 'tax'), str_contains($name, 'discount'), str_contains($name, 'rate') => 'number',
            str_contains($name, 'percent'), str_contains($name, 'weight'), str_contains($name, 'rating') => 'number',
            str_contains($name, 'latitude'), str_contains($name, 'longitude') => 'number',

            // Common integer fields
            in_array($name, ['age', 'year', 'month', 'day', 'hour', 'minute', 'second']) => 'integer',
            in_array($name, ['order', 'position', 'priority', 'level', 'sort', 'rank']) => 'integer',
            in_array($name, ['page', 'per_page', 'limit', 'offset', 'skip']) => 'integer',
            in_array($name, ['width', 'height', 'size', 'duration']) => 'integer',

            // Common boolean fields
            in_array($name, ['active', 'enabled', 'visible', 'published', 'verified']) => 'boolean',
            in_array($name, ['mandatory', 'filterable', 'unique', 'localizable', 'is_multiple']) => 'boolean',
            in_array($name, ['disabled', 'hidden', 'deleted', 'blocked', 'banned', 'expired', 'archived']) => 'boolean',

            default => 'string',
        };
    }

    /**
     * Resolve type from Laravel validation rule.
     */
    public function fromValidationRule(string $rule): string
    {
        $name = explode(':', $rule, 2)[0];

        return $this->typeMap[$name] ?? match ($name) {
            'int', 'integer' => 'integer',
            'numeric', 'decimal', 'float', 'double', 'number' => 'number',
            'bool', 'boolean' => 'boolean',
            'array' => 'array',
            'object' => 'object',
            default => 'string',
        };
    }

    public function fromRules(array $rules): string
    {
        foreach ($rules as $rule) {
            if (! is_string($rule)) {
                continue;
            }
            $name = explode(':', $rule, 2)[0];
            if (isset($this->typeMap[$name]) || in_array($name, ['int', 'integer', 'numeric', 'decimal', 'float', 'double', 'number', 'bool', 'boolean', 'array', 'object', 'string', 'email', 'url', 'uuid', 'date', 'file', 'image', 'json'], true)) {
                return $this->fromValidationRule($rule);
            }
        }

        return 'string';
    }

    public function schema(string $type): array
    {
        if (str_ends_with($type, '[]')) {
            return ['type' => 'array', 'items' => $this->schema(substr($type, 0, -2))];
        }

        return match (strtolower($type)) {
            'int', 'integer' => ['type' => 'integer'],
            'numeric', 'float', 'double', 'decimal', 'number' => ['type' => 'number'],
            'bool', 'boolean' => ['type' => 'boolean'],
            'array' => ['type' => 'array', 'items' => (object) []],
            'object' => ['type' => 'object'],
            'file', 'image' => ['type' => 'string', 'format' => 'binary'],
            default => ['type' => 'string'],
        };
    }

    public function fromCast(string $cast): array
    {
        if (enum_exists($cast)) {
            $values = array_map(fn ($case) => $case instanceof BackedEnum ? $case->value : $case->name, $cast::cases());

            return array_filter([
                'type' => isset($values[0]) && is_int($values[0]) ? 'integer' : 'string',
                'enum' => $values ?: null,
            ], fn ($value) => $value !== null);
        }
        if (str_starts_with($cast, 'encrypted:')) {
            $cast = substr($cast, 10);
        }
        $name = strtolower(explode(':', $cast, 2)[0]);

        return match ($name) {
            'int', 'integer', 'timestamp' => ['type' => 'integer'],
            'real', 'float', 'double', 'decimal' => ['type' => 'number'],
            'bool', 'boolean' => ['type' => 'boolean'],
            'array', 'json', 'collection' => ['type' => 'array', 'items' => (object) []],
            'object' => ['type' => 'object'],
            'date', 'immutable_date' => ['type' => 'string', 'format' => 'date'],
            'datetime', 'immutable_datetime' => ['type' => 'string', 'format' => 'date-time'],
            default => ['type' => 'string'],
        };
    }
}
