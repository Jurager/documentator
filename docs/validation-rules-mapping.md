---
title: Validation Rules
weight: 80
---

# Validation Rules

Documentator uses validation rules to describe request fields.

| Rule | OpenAPI type |
| --- | --- |
| `integer`, `int` | `integer` |
| `numeric`, `decimal`, `float`, `double`, `number` | `number` |
| `boolean`, `bool` | `boolean` |
| `array` | `array` |
| `object` | `object` |
| `string`, `json`, `file`, `image` | `string` |

The `email`, `url`, `uuid`, and `date` rules add their corresponding formats. File and image fields use the binary format.

## Constraints

`required` makes a field mandatory on its containing object. `nullable` allows null. A `sometimes|required` field remains optional in the generated schema.

`min` and `max` describe string length, array size, or numeric bounds. File size rules are not converted into string lengths.

`in` and `Rule::in(...)` provide allowed values. Unique and existence rules add descriptions. Other rule objects and conditional rules may need [explicit documentation](phpdoc-annotations.md#request-fields).

## Custom Types

Add or override rule types in `config/documentator.php`:

```php
'type_map' => [
    'money' => 'number',
],
```

This mapping describes the rule in OpenAPI; it does not register a Laravel validation rule.
