---
title: Response Formats
weight: 60
---

# Response Formats

Response formats describe the envelope returned by your API. POST operations use status 201, DELETE uses 204 without a body, and other operations use 200. Use [`@response`](phpdoc-annotations.md#responses) to document other responses.

## Simple Responses

The default format describes responses containing a `success` flag and `data`:

```json
{
    "success": true,
    "data": {
        "id": 42,
        "name": "Alex"
    }
}
```

Collections contain a `data` array and pagination metadata.

## JSON:API Responses

Select `JsonApiFormat` using the `format` option in `config/documentator.php`:

```php
'format' => Jurager\Documentator\Formats\JsonApiFormat::class,
```

Responses use the `application/vnd.api+json` media type:

```json
{
    "data": {
        "type": "user",
        "id": "42",
        "attributes": {
            "name": "Alex"
        }
    }
}
```

## Custom Formats

Extend a built-in format to customize its response schema. For example, this format adds a request identifier:

```php
namespace App\OpenApi;

use Jurager\Documentator\Formats\SimpleFormat;

class ApiFormat extends SimpleFormat
{
    public function response(string $resource, array $data = [], bool $collection = false): array
    {
        $response = parent::response($resource, $data, $collection);
        $response['content']['application/json']['schema']['properties']['request_id'] = [
            'type' => 'string',
            'format' => 'uuid',
        ];

        return $response;
    }
}
```

Select the class in your configuration:

```php
'format' => App\OpenApi\ApiFormat::class,
```

Laravel resolves format dependencies through the container.

For a fully custom format, implement `Jurager\Documentator\Contracts\ResponseFormat` and provide `schemas()`, `responses()`, and `response()`.
