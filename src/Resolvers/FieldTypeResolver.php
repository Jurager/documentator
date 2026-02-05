<?php

namespace Jurager\Documentator\Resolvers;

use ReflectionNamedType;
use ReflectionType;

class FieldTypeResolver
{
    /**
     * Resolve type from field name.
     */
    public function fromFieldName(string $name): string
    {
        $n = strtolower($name);

        return match (true) {
            // ID fields
            str_ends_with($n, '_id'), $n === 'id' => 'integer',

            // Timestamps
            str_ends_with($n, '_at') => 'string', // ISO 8601 datetime

            // Counts and quantities
            str_ends_with($n, '_count') => 'integer',
            str_contains($n, 'count'), str_contains($n, 'quantity'), str_contains($n, 'stock') => 'integer',

            // Numeric fields
            str_contains($n, 'price'), str_contains($n, 'amount'), str_contains($n, 'cost') => 'number',
            str_contains($n, 'total'), str_contains($n, 'sum'), str_contains($n, 'balance') => 'number',
            str_contains($n, 'tax'), str_contains($n, 'discount'), str_contains($n, 'rate') => 'number',
            str_contains($n, 'percent'), str_contains($n, 'weight'), str_contains($n, 'rating') => 'number',
            str_contains($n, 'latitude'), str_contains($n, 'longitude') => 'number',

            // Boolean prefixes
            str_starts_with($n, 'is_'), str_starts_with($n, 'has_'), str_starts_with($n, 'can_') => 'boolean',

            // Common integer fields
            in_array($n, ['age', 'year', 'month', 'day', 'hour', 'minute', 'second']) => 'integer',
            in_array($n, ['order', 'position', 'priority', 'level', 'sort', 'rank']) => 'integer',
            in_array($n, ['page', 'per_page', 'limit', 'offset', 'skip']) => 'integer',
            in_array($n, ['width', 'height', 'size', 'duration']) => 'integer',

            // Common boolean fields
            in_array($n, ['active', 'enabled', 'visible', 'published', 'verified']) => 'boolean',
            in_array($n, ['mandatory', 'filterable', 'unique', 'localizable', 'is_multiple']) => 'boolean',
            in_array($n, ['disabled', 'hidden', 'deleted', 'blocked', 'banned', 'expired', 'archived']) => 'boolean',

            default => 'string',
        };
    }

    /**
     * Resolve type from Laravel validation rule.
     */
    public function fromValidationRule(string $rule): string
    {
        return match (true) {
            str_starts_with($rule, 'integer'), str_starts_with($rule, 'numeric') => 'integer',
            str_starts_with($rule, 'boolean'), str_starts_with($rule, 'bool') => 'boolean',
            str_starts_with($rule, 'array') => 'array',
            str_starts_with($rule, 'email') => 'string',
            str_starts_with($rule, 'url') => 'string',
            str_starts_with($rule, 'date') => 'string',
            str_starts_with($rule, 'string') => 'string',
            default => 'string',
        };
    }

    /**
     * Resolve type from PHP reflection type.
     */
    public function fromReflectionType(?ReflectionType $type): string
    {
        if (! $type instanceof ReflectionNamedType) {
            return 'string';
        }

        return match ($type->getName()) {
            'int' => 'integer',
            'float', 'double' => 'number',
            'bool' => 'boolean',
            'array' => 'array',
            'object' => 'object',
            default => 'string',
        };
    }

    /**
     * Resolve type with priority: validation rule > reflection type > field name.
     */
    public function resolve(string $fieldName, ?string $validationRule = null, ?ReflectionType $reflectionType = null): string
    {
        // Validation rules have the highest priority
        if ($validationRule) {
            return $this->fromValidationRule($validationRule);
        }

        // Reflection types have second priority
        if ($reflectionType) {
            $type = $this->fromReflectionType($reflectionType);
            if ($type !== 'string') {
                return $type;
            }
        }

        // Field name patterns have the lowest priority
        return $this->fromFieldName($fieldName);
    }
}
