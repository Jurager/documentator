<?php

use Jurager\Documentator\Formats\SimpleFormat;

return [

    /*
    |--------------------------------------------------------------------------
    | OpenAPI
    |--------------------------------------------------------------------------
    |
    | OpenAPI 3.0.x version and API information shown in your documentation.
    |
    */

    'openapi_version' => '3.0.3',

    'info' => [
        'title' => env('OPENAPI_TITLE', env('APP_NAME', 'Laravel').' API'),
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

    /*
    |--------------------------------------------------------------------------
    | Servers
    |--------------------------------------------------------------------------
    |
    | Base URLs for the documented API environments.
    |
    */

    'servers' => [
        ['url' => env('APP_URL', 'http://localhost')],
    ],

    /*
    |--------------------------------------------------------------------------
    | Routes
    |--------------------------------------------------------------------------
    |
    | URI patterns, middleware and HTTP methods included in the specification.
    | Exclusions take priority over include patterns.
    |
    */

    'routes' => [
        'include' => ['api/*'],
        'exclude' => ['sanctum/*', 'horizon/*', 'telescope/*', '_ignition/*', '__clockwork/*', 'livewire/*'],
        'exclude_middleware' => [],
        'methods' => ['GET', 'POST', 'PUT', 'PATCH', 'DELETE'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Output
    |--------------------------------------------------------------------------
    |
    | JSON output path and formatting. Relative paths start at the application
    | root.
    |
    */

    'output' => [
        'path' => env('OPENAPI_OUTPUT', 'docs/openapi.json'),
        'pretty_print' => env('OPENAPI_PRETTY_PRINT', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | Response Format
    |--------------------------------------------------------------------------
    |
    | Response format used to document your API. Select a built-in format or
    | your own response format class.
    |
    */

    'format' => env('OPENAPI_FORMAT', SimpleFormat::class),

    /*
    |--------------------------------------------------------------------------
    | Security
    |--------------------------------------------------------------------------
    |
    | Authentication schemes and their defaults. Use @unauthenticated on public
    | endpoints, or an empty default array to disable default authentication.
    |
    */

    'security' => [
        'schemes' => [
            'bearerAuth' => [
                'type' => 'http',
                'scheme' => 'bearer',
                'bearerFormat' => 'JWT',
            ],
        ],
        'default' => ['bearerAuth'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Resources
    |--------------------------------------------------------------------------
    |
    | Namespaces searched for API resources and Eloquent models.
    |
    */

    'resources' => [
        'namespaces' => ['App\\Http\\Resources', 'App\\Models'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Examples
    |--------------------------------------------------------------------------
    |
    | Automatic examples. An integer seed makes generation repeatable; use null
    | for unseeded values. Explicit PHPDoc examples are always kept.
    |
    */

    'examples' => [
        'enabled' => true,
        'seed' => 0,
    ],

    /*
    |--------------------------------------------------------------------------
    | Validation Types
    |--------------------------------------------------------------------------
    |
    | Additional validation-rule mappings to OpenAPI types. Built-in rules are
    | supported without entries here.
    |
    */

    'type_map' => [],

    /*
    |--------------------------------------------------------------------------
    | Tags
    |--------------------------------------------------------------------------
    |
    | Endpoint groups and their descriptions. Missing groups may be generated
    | and sorted automatically.
    |
    */

    'tags' => [
        'auto_generate' => true,
        'definitions' => [],
        'sort' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Parameter Presets
    |--------------------------------------------------------------------------
    |
    | Shared query and header parameters applied with @preset <name>.
    |
    */

    'presets' => [
        'pagination' => [
            'query' => [
                'page[size] integer optional Number of items per page. Default: 15.',
                'page[number] integer optional Page number. Default: 1.',
            ],
        ],
        'accept-language' => [
            'header' => [
                'Accept-Language string optional Preferred language.',
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Responses
    |--------------------------------------------------------------------------
    |
    | Responses added to every operation and descriptions for HTTP status codes.
    |
    */

    'responses' => [
        'default' => [],
        'descriptions' => [],
    ],
];
