---
title: Validation Rules Mapping
weight: 80
---

# Validation Rules Mapping

Schema type detection uses `type_map` from config.

Default mapping includes:

- `integer`, `int`, `numeric` -> `integer`
- `boolean`, `bool` -> `boolean`
- `float`, `double`, `decimal`, `number` -> `number`
- `string` -> `string`
- `array` -> `array`
- `json`, `object` -> `object`
- `file`, `image`, `mimes` -> `string`
- `date`, `datetime`, `timestamp` -> `string`

## Rule Attributes

Specific rules are also translated to schema attributes:

- `min:N` -> `minLength` / `minimum`
- `max:N` -> `maxLength` / `maximum`
- `in:a,b,c` -> `enum`
- `email` -> `format: email`
- `url` -> `format: uri`
- `uuid` -> `format: uuid`
- `nullable` -> `nullable: true`

## Override Mapping

Update `type_map` in `config/documentator.php` to change type behavior globally.
