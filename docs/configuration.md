---
title: Configuration
weight: 30
---

# Configuration

Publish `config/documentator.php` to customize generation:

```bash
php artisan vendor:publish --tag=documentator-config
```

## API Information

Set the name and version shown in your documentation:

```php
'info' => [
    'title' => 'Acme API',
    'version' => '1.0.0',
    'description' => 'Manage your Acme account.',
],

'servers' => [
    ['url' => 'https://api.example.com'],
],
```

Documentator generates OpenAPI 3.0.x documents.

## Routes

Choose routes using URI patterns. Excluded routes take priority over included routes:

```php
'routes' => [
    'include' => ['api/*'],
    'exclude' => ['api/internal/*'],
    'exclude_middleware' => ['internal'],
    'methods' => ['GET', 'POST', 'PUT', 'PATCH', 'DELETE'],
],
```

## Authentication

The default configuration documents bearer authentication. Define the schemes used by your API and select their defaults:

```php
'security' => [
    'schemes' => [
        'bearerAuth' => [
            'type' => 'http',
            'scheme' => 'bearer',
        ],
    ],
    'default' => ['bearerAuth'],
],
```

Add `@unauthenticated` to public endpoints. Set `security.default` to `[]` when your API has no default authentication.

## Output

The command writes JSON to the configured path, relative to your application:

```php
'output' => [
    'path' => 'public/openapi.json',
    'pretty_print' => true,
],
```

You may also pass `--output=public/openapi.json` when running the command.

## Response Format

Choose the format matching your API's responses:

```php
'format' => Jurager\Documentator\Formats\SimpleFormat::class,
```

See [Response Formats](response-formats.md) for examples and custom formats.

## Resource Discovery

Add namespaces when your resources or models live outside Laravel's default directories:

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

## Tags and Default Responses

Use `@group Users` to group endpoints. You may add descriptions for those groups:

```php
'tags' => [
    'auto_generate' => true,
    'definitions' => ['Users' => 'Manage users and their profiles.'],
    'sort' => true,
],
```

To document a response on every operation, add a reusable response reference:

```php
'responses' => [
    'default' => [
        401 => ['$ref' => '#/components/responses/Unauthorized'],
    ],
],
```

For shared parameters, see [Presets](phpdoc-annotations.md#presets). To customize field types, see [Validation Rules](validation-rules-mapping.md).

## Examples

Automatic examples use a fixed seed so repeated generation produces the same document:

```php
'examples' => [
    'enabled' => true,
    'seed' => 0,
],
```

Set `enabled` to `false` to omit automatic examples. Explicit PHPDoc examples are kept. Use another integer seed for different sample values, or `null` for unseeded generation.

Descriptions use your application's active locale. Faker uses `app.faker_locale`.
