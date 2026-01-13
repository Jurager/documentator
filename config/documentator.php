<?php

return [

    /*
    |--------------------------------------------------------------------------
    | API Information
    |--------------------------------------------------------------------------
    */

    'title' => env('OPENAPI_TITLE', 'API Documentation'),

    'version' => env('OPENAPI_VERSION', '1.0.0'),

    'description' => null,

    /*
    |--------------------------------------------------------------------------
    | Output Configuration
    |--------------------------------------------------------------------------
    */

    'output' => env('OPENAPI_OUTPUT', 'docs/openapi.json'),

    /*
    |--------------------------------------------------------------------------
    | Server Configuration
    |--------------------------------------------------------------------------
    */

    'servers' => [
        [
            'url' => env('APP_URL', 'http://localhost'),
            'description' => 'Default server',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Security Schemes
    |--------------------------------------------------------------------------
    |
    | Define authentication methods for your API.
    | Set to empty array to disable security documentation.
    |
    */

    'security' => [
        'schemes' => [
            'bearerAuth' => [
                'type' => 'http',
                'scheme' => 'bearer',
                'bearerFormat' => 'JWT',
            ],
            // 'apiKey' => [
            //     'type' => 'apiKey',
            //     'in' => 'header',
            //     'name' => 'X-API-Key',
            // ],
        ],

        // Default security applied to all endpoints (can be overridden per route)
        'default' => ['bearerAuth'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Response Format
    |--------------------------------------------------------------------------
    |
    | Supported: "simple", "json-api"
    | You can also specify a custom class implementing ResponseFormat interface.
    |
    */

    'format' => env('OPENAPI_FORMAT', 'simple'),

    /*
    |--------------------------------------------------------------------------
    | Custom Formats
    |--------------------------------------------------------------------------
    |
    | Register custom response formats here.
    | Key is the format name, value is the fully qualified class name.
    |
    */

    'formats' => [
        // 'custom' => App\OpenApi\CustomResponseFormat::class,
    ],

    /*
    |--------------------------------------------------------------------------
    | Route Filtering
    |--------------------------------------------------------------------------
    */

    'routes' => [

        // Include only routes matching these patterns (empty = all routes)
        'include' => [
            'api/*',
        ],

        // Exclude routes matching these patterns
        'exclude' => [
            'sanctum/*',
            'horizon/*',
            '_ignition/*',
            '__clockwork/*',
        ],

        // Exclude routes with this middleware
        'exclude_middleware' => [
            // 'auth:sanctum',
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | HTTP Methods
    |--------------------------------------------------------------------------
    */

    'methods' => ['get', 'post', 'put', 'patch', 'delete'],

    /*
    |--------------------------------------------------------------------------
    | Localization
    |--------------------------------------------------------------------------
    |
    | Language for auto-generated summaries and descriptions.
    | Supported: "en", "ru"
    |
    */

    'locale' => env('OPENAPI_LOCALE', 'en'),

    /*
    |--------------------------------------------------------------------------
    | HTTP Status Descriptions
    |--------------------------------------------------------------------------
    */

    'status_descriptions' => [
        200 => 'Successful response',
        201 => 'Resource created',
        204 => 'Resource deleted',
        400 => 'Bad request',
        401 => 'Unauthorized',
        403 => 'Forbidden',
        404 => 'Resource not found',
        422 => 'Validation error',
        500 => 'Internal server error',
    ],

    /*
    |--------------------------------------------------------------------------
    | Validation Rules to OpenAPI Type Mapping
    |--------------------------------------------------------------------------
    */

    'type_map' => [
        'int' => 'integer',
        'integer' => 'integer',
        'numeric' => 'integer',
        'bool' => 'boolean',
        'boolean' => 'boolean',
        'float' => 'number',
        'double' => 'number',
        'number' => 'number',
        'array' => 'array',
        'object' => 'object',
        'file' => 'string',
        'image' => 'string',
    ],

    /*
    |--------------------------------------------------------------------------
    | Tags / Groups
    |--------------------------------------------------------------------------
    |
    | Pre-define tags with descriptions for better documentation organization.
    |
    */

    'tags' => [
        // 'Users' => 'User management endpoints',
        // 'Auth' => 'Authentication and authorization',
    ],

    /*
    |--------------------------------------------------------------------------
    | Default Responses
    |--------------------------------------------------------------------------
    |
    | Add these responses to all endpoints automatically.
    |
    */

    'default_responses' => [
        // '401' => ['$ref' => '#/components/responses/Unauthorized'],
        // '500' => ['$ref' => '#/components/responses/ServerError'],
    ],

];
