<?php

namespace Jurager\Documentator\Support;

class RuleNormalizer
{
    /**
     * Привести значение правила поля к списку токенов для интроспекции.
     *
     * string  → explode('|') ("required|integer" → ['required','integer'])
     * array   → как есть (элементы могут быть строками или Rule-объектами)
     * прочее  → [] (Rule::forEach()/NestedRules, замыкания, объекты — не интроспектируем)
     *
     * @return array<int, mixed>
     */
    public static function tokens(mixed $rule): array
    {
        return match (true) {
            is_array($rule)  => $rule,
            is_string($rule) => explode('|', $rule),
            default          => [],
        };
    }
}
