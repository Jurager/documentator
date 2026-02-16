---
title: Schema Detection
weight: 50
---

# Schema Detection

Documentator builds request and response schemas from your Laravel code.

## Request Schema Sources

- `FormRequest::rules()` from controller method parameters.
- Inline validation from `$request->validate([...])` calls.
- `@bodyParam` tags in PHPDoc.

For `POST`, `PUT`, and `PATCH`, request body schemas are generated and referenced as OpenAPI components.

> [!WARNING]
> Inline validation extraction is based on static parsing of `$request->validate([...])`. Highly dynamic rule construction may not be detected.

## Response Schema Sources

- Resource class return type on controller methods.
- `Resource::make(...)`, `Resource::collection(...)`, or `new Resource(...)` usage in method body.
- Fallback resource class guessing from route segment + configured namespaces.

If no resource is detected, format-level generic examples are used.

> [!NOTE]
> Resource detection checks return types, `Resource::make(...)`, `Resource::collection(...)`, `new Resource(...)`, and `::class` usage in method body.

## Route Parameters

Path parameters from route URIs are always added to operation parameters and removed from generated request body schema.

## Explicit Responses

If PHPDoc contains `@response`, those responses take priority over auto-generated format responses.

> [!WARNING]
> When `@response` is used, generator outputs your explicit example as-is and skips automatic format envelope generation for that status set.
