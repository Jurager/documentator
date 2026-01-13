# Laravel OpenAPI Generator

Generate OpenAPI 3.0 specification from Laravel routes with automatic schema extraction from FormRequest validation rules.

## Features

- 🚀 Automatic schema generation from FormRequest validation rules
- 📝 PHPDoc annotations support (@summary, @group, @response, etc.)
- 🔐 Security schemes (Bearer, API Key, OAuth2)
- 🌍 Localization (en, ru)
- 🎨 Multiple response formats (REST, JSON:API, custom)
- ⚙️ Fully configurable via config file

## Installation

```bash
composer require jurager/documentator
```

Publish configuration:

```bash
php artisan vendor:publish --tag=documentator-config
```

## Usage

```bash
php artisan docs:generate
```

Options:
```bash
php artisan docs:generate --output=public/api.json
php artisan docs:generate --format=json-api
```

## Configuration

```php
// config/documentator.php

return [
    // API metadata
    'title' => env('OPENAPI_TITLE', 'API Documentation'),
    'version' => env('OPENAPI_VERSION', '1.0.0'),
    'description' => null,

    // Output path
    'output' => 'docs/openapi.json',

    // API servers
    'servers' => [
        ['url' => env('APP_URL'), 'description' => 'Production'],
    ],

    // Security configuration
    'security' => [
        'schemes' => [
            'bearerAuth' => [
                'type' => 'http',
                'scheme' => 'bearer',
                'bearerFormat' => 'JWT',
            ],
        ],
        'default' => ['bearerAuth'], // Applied to all endpoints
    ],

    // Response format: 'simple', 'json-api', or custom class
    'format' => 'simple',

    // Custom formats
    'formats' => [
        // 'telegram' => App\OpenApi\TelegramFormat::class,
    ],

    // Route filtering
    'routes' => [
        'include' => ['api/*'],
        'exclude' => ['sanctum/*', 'horizon/*'],
        'exclude_middleware' => [],
    ],

    // HTTP methods
    'methods' => ['get', 'post', 'put', 'patch', 'delete'],

    // Localization: 'en' or 'ru'
    'locale' => 'en',

    // HTTP status descriptions
    'status_descriptions' => [
        200 => 'Successful response',
        201 => 'Resource created',
        // ...
    ],

    // Validation rule to OpenAPI type mapping
    'type_map' => [
        'integer' => 'integer',
        'boolean' => 'boolean',
        'array' => 'array',
        // ...
    ],

    // Pre-defined tags with descriptions
    'tags' => [
        'Users' => 'User management',
        'Auth' => 'Authentication',
    ],

    // Default responses for all endpoints
    'default_responses' => [
        '401' => ['$ref' => '#/components/responses/Unauthorized'],
    ],
];
```

## PHPDoc Annotations

```php
/**
 * Get list of users
 *
 * Detailed description goes here.
 * Can be multiline.
 *
 * @group Users
 * @queryParam page integer Page number
 * @queryParam per_page integer Items per page
 * @response 200 {"data": [{"id": 1, "name": "John"}]}
 */
public function index()
{
}

/**
 * Create user
 *
 * @group Users
 * @bodyParam name string required User name
 * @bodyParam email string required Email address
 * @response 201 {"data": {"id": 1}}
 */
public function store(StoreUserRequest $request)
{
}

/**
 * @deprecated
 * @unauthenticated
 */
public function legacyEndpoint()
{
}
```

### Available Annotations

| Annotation | Description |
|------------|-------------|
| `@summary text` | Short description |
| `@description text` | Detailed description |
| `@group Name` | Tag/group name |
| `@resource name` | Override resource name |
| `@queryParam name type [required] desc` | Query parameter |
| `@bodyParam name type [required] desc` | Body parameter |
| `@urlParam name type [required] desc` | URL/path parameter |
| `@response status {"json"}` | Response example |
| `@deprecated` | Mark as deprecated |
| `@authenticated` | Requires authentication |
| `@unauthenticated` | Public endpoint (no auth) |

## Custom Response Format

```php
namespace App\OpenApi;

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

Register in config:

```php
'formats' => [
    'telegram' => App\OpenApi\TelegramFormat::class,
],
'format' => 'telegram',
```

## Validation Rules

Automatically extracts from:

1. **FormRequest** - `rules()` method
2. **Inline validation** - `$request->validate([...])`

Supported rules → OpenAPI mapping:

| Rule | OpenAPI |
|------|---------|
| `integer`, `numeric` | `type: integer` |
| `boolean` | `type: boolean` |
| `array` | `type: array` |
| `email` | `format: email` |
| `url` | `format: uri` |
| `uuid` | `format: uuid` |
| `date` | `format: date` |
| `min:N` | `minLength` / `minimum` |
| `max:N` | `maxLength` / `maximum` |
| `in:a,b,c` | `enum: [a, b, c]` |
| `nullable` | `nullable: true` |

## License

MIT