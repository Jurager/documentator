# Jurager/Documentator

[![Latest Stable Version](https://poser.pugx.org/jurager/documentator/v/stable)](https://packagist.org/packages/jurager/documentator)
[![Total Downloads](https://poser.pugx.org/jurager/documentator/downloads)](https://packagist.org/packages/jurager/documentator)
[![PHP Version Require](https://poser.pugx.org/jurager/documentator/require/php)](https://packagist.org/packages/jurager/documentator)
[![License](https://poser.pugx.org/jurager/documentator/license)](https://packagist.org/packages/jurager/documentator)

Generate OpenAPI from Laravel routes with automatic schema extraction from Form Requests, validation rules, and API Resources.

> [!NOTE]
> The documentation for this package is currently being written. For now, please refer to this readme for information on the functionality and usage of the package.


- [Features](#features)
- [Requirements](#requirements)
- [Quick Start](#quick-start)
- [Usage](#usage)
- [Configuration](#configuration)
- [How Schema Detection Works](#how-schema-detection-works)
- [PHPDoc Annotations](#phpdoc-annotations)
- [Custom Response Format](#custom-response-format)
- [Validation Rules Mapping](#validation-rules-mapping)
- [Troubleshooting](#troubleshooting)
- [License](#license)

## Features

- Automatic OpenAPI generation from Laravel routes
- Schema detection from FormRequest, inline validation, and API Resources
- Built-in formats: REST (`simple`) and JSON:API (`json-api`)
- PHPDoc-driven docs: `@summary`, `@group`, `@response`, etc.
- Built-in security schemes support (Bearer, API key, OAuth2, OpenID)
- Example payload generation via FakerPHP
- Configurable output and route filtering

## Requirements

- PHP 8.1+
- Laravel 10.x or 11.x

## Quick Start

Install the package:

```bash
composer require jurager/documentator
```

Publish config:

```bash
php artisan vendor:publish --tag=documentator-config
```

Include your API routes in `config/documentator.php`:

```php
'routes' => [
    'include' => ['api/*'],
],
```

Generate spec:

```bash
php artisan docs:generate
```

Open result file:

`docs/openapi.json`

Import it into Swagger UI, Postman, Insomnia, or any OpenAPI-compatible tool.

## Usage

Basic command:

```bash
php artisan docs:generate
```

Options:

```bash
# Override output path
php artisan docs:generate --output=public/api.json

# Override response envelope format (simple | json-api | custom)
php artisan docs:generate --format=json-api
```

## Configuration

Main config file: `config/documentator.php`

### 1) API metadata and output

```php
'openapi_version' => '3.0.3',

'info' => [
    'title' => env('OPENAPI_TITLE', 'API Documentation'),
    'version' => env('OPENAPI_VERSION', '1.0.0'),
    'description' => env('OPENAPI_DESCRIPTION'),
],

'output' => [
    'path' => env('OPENAPI_OUTPUT', 'docs/openapi.json'),
    'format' => env('OPENAPI_OUTPUT_FORMAT', 'json'), // json | yaml
    'pretty_print' => env('OPENAPI_PRETTY_PRINT', true),
],
```

### 2) Servers

```php
'servers' => [
    [
        'url' => env('APP_URL', 'http://localhost'),
        'description' => 'Development server',
    ],
    [
        'url' => 'https://api.example.com',
        'description' => 'Production server',
    ],
],
```

### 3) Security

```php
'security' => [
    'schemes' => [
        'bearerAuth' => [
            'type' => 'http',
            'scheme' => 'bearer',
            'bearerFormat' => 'JWT',
        ],
    ],

    'default' => ['bearerAuth'], // applied to all endpoints
],
```

### 4) Route discovery

```php
'routes' => [
    'include' => ['api/*'],
    'exclude' => ['sanctum/*', 'horizon/*', 'telescope/*', '_ignition/*'],
    'exclude_middleware' => ['web'],
    'methods' => ['GET', 'POST', 'PUT', 'PATCH', 'DELETE'],
],
```

### 5) Response format

```php
'format' => env('OPENAPI_FORMAT', 'simple'), // simple | json-api

'custom_formats' => [
    'hal' => App\OpenApi\Formats\HalFormat::class,
],
```

### 6) Resources and examples

```php
'resources' => [
    'namespaces' => [
        'App\\Http\\Resources',
        'App\\Models',
    ],
    'suffix' => 'Resource',
],

'examples' => [
    'enabled' => true,
    'locale' => env('FAKER_LOCALE', 'en_US'),
    'seed' => null,
    'collection_size' => 2,
],
```

### 7) Tags and default responses

```php
'tags' => [
    'auto_generate' => true,
    'definitions' => [
        'Users' => 'User management and profiles',
        'Auth' => 'Authentication endpoints',
    ],
    'sort' => true,
],

'responses' => [
    'default' => [
        '401' => ['$ref' => '#/components/responses/Unauthorized'],
        '403' => ['$ref' => '#/components/responses/Forbidden'],
    ],
],
```

### 8) Advanced options

```php
'advanced' => [
    'cache_parsed_files' => true,
    'include_deprecated' => false,
    'validate_schemas' => env('OPENAPI_VALIDATE', true),
    'deep_scan_controllers' => true,
],
```

## How Schema Detection Works

Documentator builds request/response schemas from:

1. FormRequest `rules()`
2. Inline validation (`$request->validate([...])`)
3. Controller validation (`validate(...)`)
4. API Resources (`JsonResource`)

Example:

```php
class UserResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'created_at' => $this->created_at,
        ];
    }
}
```

## PHPDoc Annotations

Example:

```php
/**
 * Get users
 *
 * @group Users
 * @queryParam page integer Page number
 * @queryParam per_page integer Items per page
 * @response 200 {"data": [{"id": 1, "name": "John"}]}
 */
public function index()
{
}
```

Supported annotations:

| Annotation | Purpose |
|---|---|
| `@summary text` | Short endpoint summary |
| `@description text` | Detailed endpoint description |
| `@group Name` | Tag/group name |
| `@resource name` | Override detected resource name |
| `@queryParam name type [required] desc` | Query parameter |
| `@bodyParam name type [required] desc` | Body parameter |
| `@urlParam name type [required] desc` | URL/path parameter |
| `@response status {"json"}` | Response example |
| `@deprecated` | Mark endpoint as deprecated |
| `@authenticated` | Requires authentication |
| `@unauthenticated` | Public endpoint |

## Custom Response Format

Create a class extending `Jurager\Documentator\Formats\AbstractFormat`:

```php
namespace App\Documentator;

use Jurager\Documentator\Formats\AbstractFormat;

class TelegramFormat extends AbstractFormat
{
    public function name(): string
    {
        return 'telegram';
    }

    public function description(): string
    {
        return 'Telegram Bot API style responses';
    }

    protected function successSchema(): string
    {
        return 'TelegramSuccess';
    }

    protected function errorSchema(): string
    {
        return 'TelegramError';
    }

    public function schemas(): array
    {
        return [
            'TelegramSuccess' => [
                'type' => 'object',
                'required' => ['ok', 'result'],
                'properties' => [
                    'ok' => ['type' => 'boolean', 'example' => true],
                    'result' => ['type' => 'object'],
                ],
            ],
            'TelegramError' => [
                'type' => 'object',
                'required' => ['ok', 'error_code', 'description'],
                'properties' => [
                    'ok' => ['type' => 'boolean', 'example' => false],
                    'error_code' => ['type' => 'integer'],
                    'description' => ['type' => 'string'],
                ],
            ],
        ];
    }
}
```

Register it in config:

```php
'custom_formats' => [
    'telegram' => App\Documentator\TelegramFormat::class,
],
'format' => 'telegram',
```

## Validation Rules Mapping

| Laravel Rule | OpenAPI |
|---|---|
| `integer`, `int`, `numeric` | `type: integer` |
| `boolean`, `bool` | `type: boolean` |
| `string` | `type: string` |
| `email` | `type: string`, `format: email` |
| `url` | `type: string`, `format: uri` |
| `uuid` | `type: string`, `format: uuid` |
| `date` | `type: string`, `format: date` |
| `datetime` | `type: string`, `format: date-time` |
| `array` | `type: array` |
| `json` | `type: object` |
| `file`, `image` | `type: string`, `format: binary` |
| `min:N` | `minLength` or `minimum` |
| `max:N` | `maxLength` or `maximum` |
| `in:a,b,c` | `enum: [a, b, c]` |
| `required` | required field |
| `nullable` | nullable field |

## Troubleshooting

### No routes found

If generator says no routes were found:

1. Verify `routes.include` in `config/documentator.php`.
2. Check routes are registered: `php artisan route:list`.
3. Ensure route patterns are not excluded by `routes.exclude`.
4. Verify methods are allowed in `routes.methods`.

### Empty `paths` in generated spec

1. Ensure controller classes and methods exist.
2. Avoid closure routes for endpoints you want documented.
3. Check controller loading errors in your app.

### Custom format not found

1. Register class in `custom_formats` (not `formats`).
2. Ensure class extends `AbstractFormat`.
3. Verify namespace/class name is correct.

### Resource class not detected

1. Add namespace to `resources.namespaces`.
2. Ensure class extends `JsonResource`.
3. Check class suffix matches `resources.suffix`.

## License

This package is open-sourced software licensed under the [MIT license](LICENSE.md).
