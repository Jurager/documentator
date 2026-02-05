# Jurager/Documentator

[![Latest Stable Version](https://poser.pugx.org/jurager/documentator/v/stable)](https://packagist.org/packages/jurager/documentator)
[![Total Downloads](https://poser.pugx.org/jurager/documentator/downloads)](https://packagist.org/packages/jurager/documentator)
[![PHP Version Require](https://poser.pugx.org/jurager/documentator/require/php)](https://packagist.org/packages/jurager/documentator)
[![License](https://poser.pugx.org/jurager/documentator/license)](https://packagist.org/packages/jurager/documentator)

Automatically generate OpenAPI specification from your Laravel routes with intelligent schema extraction and validation.


- [Features](#features)
- [Requirements](#requirements)
- [Installation](#installation)
- [Quick Start](#quick-start)
- [Usage](#usage)
- [Configuration](#configuration)
  - [Basic Setup](#basic-setup)
  - [Servers](#servers)
  - [Security](#security)
  - [Response Formats](#response-formats)
  - [Route Discovery](#route-discovery)
  - [Resource Discovery](#resource-discovery)
  - [Example Generation](#example-generation)
  - [Type Mapping](#type-mapping)
  - [Tags & Grouping](#tags--grouping)
  - [Default Responses](#default-responses)
  - [Advanced Options](#advanced-options)
- [Automatic Schema Detection](#automatic-schema-detection)
- [PHPDoc Annotations](#phpdoc-annotations)
- [Custom Response Format](#custom-response-format)
- [Validation Rules](#validation-rules)
- [Troubleshooting](#troubleshooting)
- [License](#license)

## Features

- **Automatic Generation** - Extracts from FormRequests, inline validation, and Resource classes
- **Multiple Formats** - Built-in REST and JSON:API, plus custom format support
- **PHPDoc Annotations** - Rich documentation through `@summary`, `@group`, `@response`, etc.
- **Smart Type Detection** - Intelligent field type resolution from validation rules
- **Security Schemes** - Bearer tokens, API Keys, OAuth2, OpenID Connect
- **Realistic Examples** - Powered by FakerPHP for authentic sample data
- **Fully Configurable** - Extensive customization options

## Requirements

- PHP 8.1 or higher
- Laravel 10.x or 11.x
- 
## Installation

```bash
composer require jurager/documentator
```

Publish configuration:

```bash
php artisan vendor:publish --tag=documentator-config
```

## Quick Start

1. Install the package:
   ```bash
   composer require jurager/documentator
   ```

2. Publish the config file:
   ```bash
   php artisan vendor:publish --tag=documentator-config
   ```

3. Configure your API routes in `config/documentator.php`:
   ```php
   'routes' => [
       'include' => ['api/*'],
   ],
   ```

4. Generate documentation:
   ```bash
   php artisan docs:generate
   ```

5. Your OpenAPI specification will be generated at `docs/openapi.json`

You can now import this file into Swagger UI, Postman, Insomnia, or any other OpenAPI-compatible tool.

## Usage

Generate documentation with a single command:

```bash
php artisan docs:generate
```

### Command Options

```bash
# Override output path
php artisan docs:generate --output=public/api.json

# Override response format
php artisan docs:generate --format=json-api
```

## Configuration

The package offers extensive configuration options through `config/documentator.php`:

### Basic Setup

```php
// OpenAPI specification version
'openapi_version' => '3.0.3',

// API information
'info' => [
    'title' => env('OPENAPI_TITLE', 'API Documentation'),
    'version' => env('OPENAPI_VERSION', '1.0.0'),
    'description' => env('OPENAPI_DESCRIPTION'),
    'contact' => [
        'name' => env('OPENAPI_CONTACT_NAME'),
        'email' => env('OPENAPI_CONTACT_EMAIL'),
        'url' => env('OPENAPI_CONTACT_URL'),
    ],
    'license' => [
        'name' => env('OPENAPI_LICENSE_NAME'),
        'url' => env('OPENAPI_LICENSE_URL'),
    ],
],

// Output configuration
'output' => [
    'path' => env('OPENAPI_OUTPUT', 'docs/openapi.json'),
    'format' => 'json', // json or yaml
    'pretty_print' => true,
],
```

### Servers

Define multiple server environments:

```php
'servers' => [
    [
        'url' => env('APP_URL', 'http://localhost'),
        'description' => 'Development server',
    ],
    [
        'url' => 'https://staging.example.com',
        'description' => 'Staging server',
    ],
    [
        'url' => 'https://api.example.com',
        'description' => 'Production server',
    ],
],
```

### Security

Configure authentication schemes:

```php
'security' => [
    'schemes' => [
        'bearerAuth' => [
            'type' => 'http',
            'scheme' => 'bearer',
            'bearerFormat' => 'JWT',
            'description' => 'Enter your bearer token',
        ],
        'apiKey' => [
            'type' => 'apiKey',
            'in' => 'header',
            'name' => 'X-API-Key',
        ],
        'oauth2' => [
            'type' => 'oauth2',
            'flows' => [
                'authorizationCode' => [
                    'authorizationUrl' => 'https://example.com/oauth/authorize',
                    'tokenUrl' => 'https://example.com/oauth/token',
                    'scopes' => [
                        'read' => 'Read access',
                        'write' => 'Write access',
                    ],
                ],
            ],
        ],
    ],
    'default' => ['bearerAuth'], // Applied to all endpoints
],
```

### Response Formats

Choose between built-in formats or create custom ones:

```php
// Built-in: 'simple' (REST) or 'json-api'
'format' => env('OPENAPI_FORMAT', 'simple'),

// Register custom formats
'custom_formats' => [
    'hal' => App\OpenApi\Formats\HalFormat::class,
],
```

### Route Discovery

Control which routes are documented:

```php
'routes' => [
    'include' => ['api/*'],
    'exclude' => [
        'sanctum/*',
        'horizon/*',
        'telescope/*',
        '_ignition/*',
    ],
    'exclude_middleware' => ['web'],
    'methods' => ['GET', 'POST', 'PUT', 'PATCH', 'DELETE'],
],
```

### Resource Discovery

Configure where to find API resources:

```php
'resources' => [
    'namespaces' => [
        'App\\Http\\Resources',
        'App\\Models',
    ],
    'suffix' => 'Resource',
],
```

### Example Generation

Control automatic example data generation:

```php
'examples' => [
    'enabled' => true,
    'locale' => 'en_US', // FakerPHP locale
    'seed' => null, // For reproducible examples
    'collection_size' => 2,
    'pagination' => [
        'total' => 100,
        'per_page' => 15,
        'current_page' => 1,
    ],
],
```

### Type Mapping

Map validation rules to OpenAPI types:

```php
'type_map' => [
    'integer' => 'integer',
    'numeric' => 'integer',
    'boolean' => 'boolean',
    'string' => 'string',
    'email' => 'string',
    'url' => 'string',
    'uuid' => 'string',
    'date' => 'string',
    'datetime' => 'string',
    'array' => 'array',
    'json' => 'object',
    'file' => 'string',
    'image' => 'string',
],
```

### Tags & Grouping

Organize endpoints into logical groups:

```php
'tags' => [
    'auto_generate' => true, // Auto-generate from route prefixes
    'definitions' => [
        'Users' => 'User management and profiles',
        'Auth' => 'Authentication endpoints',
        'Posts' => 'Blog posts and content',
    ],
    'sort' => true, // Alphabetically sort tags
],
```

### Default Responses

Add reusable responses to all endpoints:

```php
'responses' => [
    'default' => [
        '401' => ['$ref' => '#/components/responses/Unauthorized'],
        '403' => ['$ref' => '#/components/responses/Forbidden'],
        '500' => ['$ref' => '#/components/responses/ServerError'],
    ],
    'descriptions' => [
        200 => 'Successful response',
        201 => 'Resource created successfully',
        204 => 'Resource deleted successfully',
        400 => 'Bad request - Invalid input',
        401 => 'Unauthorized - Authentication required',
        403 => 'Forbidden - Insufficient permissions',
        404 => 'Resource not found',
        422 => 'Validation error',
        429 => 'Too many requests',
        500 => 'Internal server error',
    ],
],
```

### Advanced Options

```php
'advanced' => [
    'cache_parsed_files' => true,
    'include_deprecated' => false,
    'validate_schemas' => true,
    'deep_scan_controllers' => true,
],
```

## Automatic Schema Detection

The package intelligently detects response schemas from your Laravel application:

### API Resources

Automatically discovers and parses Laravel API Resource classes:

```php
namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
```

The package will automatically detect this resource and generate appropriate schemas with realistic example data using FakerPHP.

### Resource Relationships

JSON:API relationships are automatically detected:

```php
public function toArray($request)
{
    return [
        'id' => $this->id,
        'attributes' => [
            'title' => $this->title,
            'content' => $this->content,
        ],
        'relationships' => [
            'author' => new UserResource($this->author),
            'comments' => CommentResource::collection($this->comments),
        ],
    ];
}
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

Register in config:

```php
'custom_formats' => [
    'telegram' => App\Documentator\TelegramFormat::class,
],
'format' => 'telegram',
```

## Validation Rules

The package automatically extracts validation rules from:

1. **FormRequest classes** - `rules()` method
2. **Inline validation** - `$request->validate([...])`
3. **Controller method** - `validate()` calls

### Supported Rules → OpenAPI Mapping

| Laravel Rule | OpenAPI Type | Format/Additional |
|--------------|--------------|-------------------|
| `integer`, `int`, `numeric` | `integer` | - |
| `boolean`, `bool` | `boolean` | - |
| `string` | `string` | - |
| `email` | `string` | `format: email` |
| `url` | `string` | `format: uri` |
| `uuid` | `string` | `format: uuid` |
| `date` | `string` | `format: date` |
| `datetime` | `string` | `format: date-time` |
| `array` | `array` | - |
| `json` | `object` | - |
| `file`, `image` | `string` | `format: binary` |
| `min:N` | - | `minLength`/`minimum` |
| `max:N` | - | `maxLength`/`maximum` |
| `in:a,b,c` | - | `enum: [a, b, c]` |
| `required` | - | `required: true` |
| `nullable` | - | `nullable: true` |

## Troubleshooting

### No routes found

If you see "No routes were found matching your configuration":

1. Check your `routes.include` patterns in `config/documentator.php`
2. Verify routes are registered (run `php artisan route:list`)
3. Ensure routes aren't excluded by `routes.exclude` patterns
4. Check `routes.methods` includes the HTTP methods you're using

### Empty paths in generated spec

If routes are found but no endpoints are generated:

1. Verify your controllers are accessible and not throwing errors
2. Check that route actions are defined (not closures)
3. Ensure controller methods exist

### Custom format not found

When using a custom format:

1. Register it in `custom_formats` config (not `formats`)
2. Ensure the class exists and extends `AbstractFormat`
3. Check the namespace is correct

### Resource class not detected

If your API Resource classes aren't being detected:

1. Add the namespace to `resources.namespaces` in config
2. Ensure classes extend `Illuminate\Http\Resources\Json\JsonResource`
3. Check the resource naming convention matches `resources.suffix`

## License

MIT License - see [LICENSE](LICENSE) for details.