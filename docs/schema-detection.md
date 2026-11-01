---
title: Request and Response Schemas
weight: 50
---

# Request and Response Schemas

Documentator reads request fields from validation rules and response fields from API resources.

## Request Fields

Type-hint a FormRequest on your controller action:

```php
public function store(StoreUserRequest $request): UserResource
{
    return new UserResource(User::create($request->validated()));
}
```

Its rules describe the request body:

```php
public function rules(): array
{
    return [
        'name' => ['required', 'string', 'max:100'],
        'email' => ['required', 'email'],
        'roles' => ['array'],
        'roles.*' => ['string'],
    ];
}
```

Inline `$request->validate([...])` rules are also supported. Request bodies are generated for POST, PUT, PATCH, and DELETE operations.

Use [`@bodyParam`](phpdoc-annotations.md#request-fields) for descriptions, examples, or fields that depend on runtime data. Dynamic rules may require explicit documentation.

## Nested Fields and Files

Use Laravel's dot notation for objects and array items:

```php
return [
    'address.city' => ['required', 'string'],
    'items' => ['required', 'array'],
    'items.*.id' => ['required', 'integer'],
    'attachment' => ['file'],
];
```

File fields produce a `multipart/form-data` request body. Path parameters are documented separately from body fields.

## Response Fields

Return an API resource or resource collection from your controller:

```php
public function show(User $user): UserResource
{
    return new UserResource($user);
}
```

Documentator discovers resources from return types and resource calls such as `UserResource::collection(...)`. It reads resource fields without executing `toArray()`. Nested arrays, explicit PHP casts, and supported Eloquent casts describe field types; names provide a fallback.

Choose the [response format](response-formats.md) matching your API. Use [`@response`](phpdoc-annotations.md#response-examples) when an endpoint needs a specific response example.
