---
title: Request and Response Schemas
weight: 40
---

# Request and Response Schemas

## Request Bodies

Type-hint a FormRequest on your controller method to document its request fields:

```php
public function store(StoreUserRequest $request): UserResource
{
    return new UserResource(User::create($request->validated()));
}
```

The request's validation rules describe the body:

```php
public function rules(): array
{
    return [
        'name' => ['required', 'string', 'max:100'],
        'email' => ['required', 'email'],
    ];
}
```

Inline `$request->validate([...])` rules are also supported. Bodies are generated for POST, PUT, PATCH, and DELETE operations. Use [`@bodyParam`](phpdoc-annotations.md#request-fields) for fields that depend on request data.

## Nested Fields and Files

Use dot notation for nested fields and `*` for array items:

```php
return [
    'address.city' => ['required', 'string'],
    'items' => ['required', 'array'],
    'items.*.id' => ['required', 'integer'],
    'attachment' => ['file'],
];
```

File fields produce a `multipart/form-data` request body. Route parameters are documented separately.

## Validation Rules

| Rule | OpenAPI Type |
| --- | --- |
| `integer`, `int` | `integer` |
| `numeric`, `decimal`, `float`, `double`, `number` | `number` |
| `boolean`, `bool` | `boolean` |
| `array` | `array` |
| `object` | `object` |
| `string`, `json`, `file`, `image` | `string` |

The `email`, `url`, `uuid`, and `date` rules add formats. File and image fields use the `binary` format.

The `required`, `nullable`, `min`, `max`, and `in` rules describe field constraints. A `sometimes|required` field remains optional. File size constraints are not converted to string lengths.

## Custom Rule Types

Add application-specific rule types to the `type_map` option in `config/documentator.php`:

```php
'type_map' => [
    'money' => 'number',
],
```

## API Resources

Return an API resource from your controller to document response fields:

```php
public function show(User $user): UserResource
{
    return new UserResource($user);
}
```

Resource collections created with `UserResource::collection(...)` are also supported. Nested arrays, PHP casts, and Eloquent casts provide field types.

Choose the [response format](response-formats.md) used by your API. Use [`@response`](phpdoc-annotations.md#responses) for responses that need an explicit example.

## Resource Discovery

To discover resources and models in other namespaces, add them to the `resources.namespaces` option:

```php
'resources' => [
    'namespaces' => [
        'App\\Http\\Resources',
        'App\\Models',
        'Domain\\Catalog\\Resources',
        'Domain\\Catalog\\Models',
    ],
],
```
