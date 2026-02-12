<?php

return [

    /*
    |--------------------------------------------------------------------------
    | OpenAPI Specification
    |--------------------------------------------------------------------------
    |
    | Configure the OpenAPI specification version and basic metadata for your
    | API documentation. This information appears in the generated OpenAPI file.
    |
    */

    'openapi_version' => '3.0.3',

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

    /*
    |--------------------------------------------------------------------------
    | Output Configuration
    |--------------------------------------------------------------------------
    |
    | Specify where the generated OpenAPI specification file should be saved.
    | Supports both JSON and YAML formats based on file extension.
    |
    */

    'output' => [
        'path' => env('OPENAPI_OUTPUT', 'docs/openapi.json'),
        'format' => env('OPENAPI_OUTPUT_FORMAT', 'json'),
        'pretty_print' => env('OPENAPI_PRETTY_PRINT', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | Server Configuration
    |--------------------------------------------------------------------------
    |
    | Define the servers where your API is hosted. Multiple servers can be
    | specified for different environments (development, staging, production).
    |
    */

    'servers' => [
        [
            'url' => env('APP_URL', 'http://localhost'),
            'description' => 'Development server',
        ],
        // Example: Using server variables for flexible base URL in API clients
        // (Hoppscotch, Insomnia, Postman) that map variables to environments.
        // [
        //     'url' => '{protocol}://{host}',
        //     'description' => 'Configurable server',
        //     'variables' => [
        //         'protocol' => [
        //             'default' => 'https',
        //             'enum' => ['http', 'https'],
        //             'description' => 'Protocol',
        //         ],
        //         'host' => [
        //             'default' => 'api.example.com',
        //             'description' => 'API host',
        //         ],
        //     ],
        // ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Security Configuration
    |--------------------------------------------------------------------------
    |
    | Define authentication and authorization schemes for your API.
    | Common schemes: bearerAuth (JWT), apiKey, oauth2, openIdConnect.
    |
    */

    'security' => [
        'schemes' => [
            'bearerAuth' => [
                'type' => 'http',
                'scheme' => 'bearer',
                'bearerFormat' => 'JWT',
                'description' => 'Enter your bearer token in the format: Bearer {token}',
            ],
            // 'apiKey' => [
            //     'type' => 'apiKey',
            //     'in' => 'header',
            //     'name' => 'X-API-Key',
            //     'description' => 'API key for authentication',
            // ],
            // 'oauth2' => [
            //     'type' => 'oauth2',
            //     'flows' => [
            //         'authorizationCode' => [
            //             'authorizationUrl' => 'https://example.com/oauth/authorize',
            //             'tokenUrl' => 'https://example.com/oauth/token',
            //             'scopes' => [
            //                 'read' => 'Read access',
            //                 'write' => 'Write access',
            //             ],
            //         ],
            //     ],
            // ],
        ],

        'default' => ['bearerAuth'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Response Format
    |--------------------------------------------------------------------------
    |
    | Select the response format for your API documentation.
    | Built-in formats: "simple" (standard REST), "json-api" (JSON:API spec).
    | You can register custom formats in the 'custom_formats' section below.
    |
    */

    'format' => env('OPENAPI_FORMAT', 'simple'),

    'custom_formats' => [
        // 'hal' => App\OpenApi\Formats\HalFormat::class,
        // 'graphql' => App\OpenApi\Formats\GraphQLFormat::class,
    ],

    /*
    |--------------------------------------------------------------------------
    | Route Discovery
    |--------------------------------------------------------------------------
    |
    | Configure which routes should be included in the documentation.
    | Routes are filtered based on URI patterns, middleware, and HTTP methods.
    |
    */

    'routes' => [
        'include' => [
            'api/*',
        ],

        'exclude' => [
            'sanctum/*',
            'horizon/*',
            'telescope/*',
            '_ignition/*',
            '__clockwork/*',
            'livewire/*',
        ],

        'exclude_middleware' => [
            // 'web',
            // 'internal',
        ],

        'methods' => ['GET', 'POST', 'PUT', 'PATCH', 'DELETE'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Resource Discovery
    |--------------------------------------------------------------------------
    |
    | Configure namespaces where API resources and models are located.
    | Used for automatic response schema detection and example generation.
    |
    */

    'resources' => [
        'namespaces' => [
            'App\\Http\\Resources',
            'App\\Models',
        ],

        'suffix' => 'Resource',
    ],

    /*
    |--------------------------------------------------------------------------
    | Example Generation
    |--------------------------------------------------------------------------
    |
    | Configure how example data is generated for the documentation.
    | Uses FakerPHP for realistic fake data generation.
    |
    */

    'examples' => [
        'enabled' => true,

        'locale' => env('FAKER_LOCALE', 'en_US'),

        'seed' => null,

        'collection_size' => 2,

        'pagination' => [
            'total' => 100,
            'per_page' => 15,
            'current_page' => 1,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Type Mapping
    |--------------------------------------------------------------------------
    |
    | Map Laravel validation rules and PHP types to OpenAPI types.
    | Customize this mapping to match your API's data structure conventions.
    |
    */

    'type_map' => [
        'int' => 'integer',
        'integer' => 'integer',
        'numeric' => 'integer',
        'bool' => 'boolean',
        'boolean' => 'boolean',
        'float' => 'number',
        'double' => 'number',
        'decimal' => 'number',
        'number' => 'number',
        'string' => 'string',
        'email' => 'string',
        'url' => 'string',
        'uuid' => 'string',
        'json' => 'object',
        'array' => 'array',
        'object' => 'object',
        'file' => 'string',
        'image' => 'string',
        'mimes' => 'string',
        'date' => 'string',
        'datetime' => 'string',
        'timestamp' => 'string',
    ],

    /*
    |--------------------------------------------------------------------------
    | Tags Configuration
    |--------------------------------------------------------------------------
    |
    | Organize your endpoints into logical groups using tags.
    | Tags can be auto-generated from route prefixes or explicitly defined.
    |
    */

    'tags' => [
        'auto_generate' => true,

        'definitions' => [
            // 'Users' => 'User management and profile operations',
            // 'Auth' => 'Authentication and authorization endpoints',
            // 'Posts' => 'Blog posts and content management',
        ],

        'sort' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Response Definitions
    |--------------------------------------------------------------------------
    |
    | Define reusable response components that can be referenced across
    | multiple endpoints to maintain consistency and reduce duplication.
    |
    */

    'responses' => [
        'default' => [
            // '401' => ['$ref' => '#/components/responses/Unauthorized'],
            // '403' => ['$ref' => '#/components/responses/Forbidden'],
            // '500' => ['$ref' => '#/components/responses/ServerError'],
        ],

        'descriptions' => [
            200 => 'Successful response',
            201 => 'Resource created successfully',
            204 => 'Resource deleted successfully',
            400 => 'Bad request - Invalid input',
            401 => 'Unauthorized - Authentication required',
            403 => 'Forbidden - Insufficient permissions',
            404 => 'Resource not found',
            422 => 'Validation error - Invalid input data',
            429 => 'Too many requests - Rate limit exceeded',
            500 => 'Internal server error',
            503 => 'Service unavailable',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Localization
    |--------------------------------------------------------------------------
    |
    | Configure the language for auto-generated descriptions, summaries,
    | and other documentation text.
    |
    */

    'locale' => env('OPENAPI_LOCALE', 'en'),

    /*
    |--------------------------------------------------------------------------
    | Advanced Options
    |--------------------------------------------------------------------------
    |
    | Additional configuration options for fine-tuning the documentation
    | generation process and output.
    |
    */

    'advanced' => [
        'cache_parsed_files' => true,

        'include_deprecated' => false,

        'validate_schemas' => env('OPENAPI_VALIDATE', true),

        'deep_scan_controllers' => true,
    ],

];
