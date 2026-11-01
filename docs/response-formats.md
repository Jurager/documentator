---
title: Response Formats
weight: 70
---

# Response Formats

Choose the format matching the responses your API returns. This setting changes the generated documentation.

## Simple Responses

The default format describes responses with a `success` flag and `data`:

```json
{
    "success": true,
    "data": {"id": 42, "name": "Alice"}
}
```

Collections contain a `data` array and pagination metadata.

## JSON:API Responses

Select `JsonApiFormat` in your configuration:

```php
'format' => Jurager\Documentator\Formats\JsonApiFormat::class,
```

Responses use the `application/vnd.api+json` media type:

```json
{
    "data": {
        "type": "users",
        "id": "42",
        "attributes": {"name": "Alice"}
    }
}
```

POST success is documented as 201, DELETE success as 204 without a body, and other operations as 200. Use [`@response`](phpdoc-annotations.md#response-examples) to describe exceptions.

## Custom Formats

Extend a built-in format or implement `Jurager\Documentator\Contracts\ResponseFormat`. Select the class directly:

```php
'format' => App\OpenApi\AcmeFormat::class,
```

Laravel resolves the format, so you may use ordinary constructor injection.

A format provides `schemas()`, `responses()`, and `response(string $resource, array $data = [], bool $collection = false)`. The `response()` method returns OpenAPI response fields such as `content`; generation selects the HTTP status and adds examples.
