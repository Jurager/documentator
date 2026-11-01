---
title: Configuration
weight: 20
---

# Configuration

The following options are defined in your published `config/documentator.php` file.

## API Information

The `info` and `servers` options describe your API and its base URLs:

```php
'info' => [
    'title' => 'Acme API',
    'version' => '1.0.0',
],

'servers' => [
    ['url' => 'https://api.example.com'],
],
```

## Routes

Use URI patterns to choose which routes to document. Exclusions take precedence over inclusions:

```php
'routes' => [
    'include' => ['api/*'],
    'exclude' => ['api/internal/*'],
    'exclude_middleware' => ['internal'],
    'methods' => ['GET', 'POST', 'PUT', 'PATCH', 'DELETE'],
],
```

## Authentication

The default specification uses bearer authentication. Configure the schemes used by your API:

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

Use [`@unauthenticated`](phpdoc-annotations.md#authentication) for public endpoints. To omit default authentication, set `security.default` to `[]`.

## Output

The `output` option controls the generated JSON file. Relative paths are resolved from your application root:

```php
'output' => [
    'path' => 'docs/openapi.json',
    'pretty_print' => true,
],
```

## Examples

Automatic examples use a fixed seed for repeatable output:

```php
'examples' => [
    'enabled' => true,
    'seed' => 0,
],
```

Set `enabled` to `false` to omit automatic examples. Explicit PHPDoc examples are preserved. Set `seed` to another integer for different values, or `null` for random values.

## Tags

Use [`@group`](phpdoc-annotations.md#grouping-endpoints) to group endpoints. Add descriptions for these groups using the `tags.definitions` option:

```php
'tags' => [
    'definitions' => [
        'Users' => 'Manage users and their profiles.',
    ],
],
```

## Default Responses

To document a response on every operation, add it to the `responses.default` option:

```php
'responses' => [
    'default' => [
        401 => ['$ref' => '#/components/responses/Unauthorized'],
    ],
],
```

The built-in formats provide the `Unauthorized` response. You may also define responses in a [custom format](response-formats.md#custom-formats).
