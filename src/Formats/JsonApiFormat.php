<?php

namespace Jurager\Documentator\Formats;

class JsonApiFormat extends AbstractFormat
{
    public function name(): string
    {
        return 'json-api';
    }

    public function description(): string
    {
        return 'JSON:API Specification (https://jsonapi.org)';
    }

    protected function successSchema(): string
    {
        return 'JsonApiDocument';
    }

    protected function errorSchema(): string
    {
        return 'JsonApiError';
    }

    public function schemas(): array
    {
        return [
            'JsonApiResource' => [
                'type' => 'object',
                'required' => ['type', 'id'],
                'properties' => [
                    'type' => ['type' => 'string', 'description' => 'Resource type'],
                    'id' => ['type' => 'string', 'description' => 'Resource ID'],
                    'attributes' => ['type' => 'object', 'description' => 'Resource attributes'],
                    'relationships' => ['type' => 'object', 'description' => 'Resource relationships'],
                    'links' => ['type' => 'object', 'properties' => [
                        'self' => ['type' => 'string'],
                    ]],
                    'meta' => ['type' => 'object'],
                ],
            ],
            'JsonApiDocument' => [
                'type' => 'object',
                'properties' => [
                    'data' => ['oneOf' => [
                        ['$ref' => '#/components/schemas/JsonApiResource'],
                        ['type' => 'array', 'items' => ['$ref' => '#/components/schemas/JsonApiResource']],
                        ['type' => 'null'],
                    ]],
                    'included' => ['type' => 'array', 'items' => ['$ref' => '#/components/schemas/JsonApiResource']],
                    'links' => ['type' => 'object', 'properties' => [
                        'self' => ['type' => 'string'],
                        'first' => ['type' => 'string'],
                        'last' => ['type' => 'string'],
                        'prev' => ['type' => 'string', 'nullable' => true],
                        'next' => ['type' => 'string', 'nullable' => true],
                    ]],
                    'meta' => ['type' => 'object', 'properties' => [
                        'total' => ['type' => 'integer'],
                        'page' => ['type' => 'integer'],
                        'per_page' => ['type' => 'integer'],
                    ]],
                ],
            ],
            'JsonApiError' => [
                'type' => 'object',
                'required' => ['errors'],
                'properties' => [
                    'errors' => [
                        'type' => 'array',
                        'items' => [
                            'type' => 'object',
                            'properties' => [
                                'id' => ['type' => 'string', 'description' => 'Unique error identifier'],
                                'status' => ['type' => 'string', 'description' => 'HTTP status code'],
                                'code' => ['type' => 'string', 'description' => 'Application error code'],
                                'title' => ['type' => 'string', 'description' => 'Short error description'],
                                'detail' => ['type' => 'string', 'description' => 'Detailed error description'],
                                'source' => [
                                    'type' => 'object',
                                    'properties' => [
                                        'pointer' => ['type' => 'string', 'description' => 'JSON Pointer to error source'],
                                        'parameter' => ['type' => 'string', 'description' => 'Query parameter name'],
                                    ],
                                ],
                                'meta' => ['type' => 'object'],
                            ],
                        ],
                    ],
                    'meta' => ['type' => 'object'],
                ],
            ],
        ];
    }

    public function operationResponse(string $method, string $resource, ?array $attributes = null, bool $isCollection = false): array
    {
        $status = match ($method) {
            'post' => '201',
            'delete' => '204',
            default => '200',
        };

        if ($status === '204') {
            return [$status => ['description' => 'No Content']];
        }

        return [$status => [
            'description' => match ($status) {
                '201' => 'Resource created',
                default => 'Success',
            },
            'content' => [
                'application/vnd.api+json' => [
                    'schema' => $this->buildResponseSchema($resource, $attributes, $isCollection),
                ],
            ],
        ]];
    }

    private function buildResponseSchema(string $resource, ?array $attributes, bool $isCollection): array
    {
        $resourceSchema = $this->buildResourceSchema($resource, $attributes);

        if ($isCollection) {
            $total = $this->examples->faker()->numberBetween(50, 500);
            $lastPage = (int) ceil($total / 15);

            return [
                'type' => 'object',
                'required' => ['data'],
                'properties' => [
                    'data' => [
                        'type' => 'array',
                        'items' => $resourceSchema,
                    ],
                    'links' => [
                        'type' => 'object',
                        'properties' => [
                            'self' => ['type' => 'string', 'example' => "/{$resource}?page[number]=1"],
                            'first' => ['type' => 'string', 'example' => "/{$resource}?page[number]=1"],
                            'last' => ['type' => 'string', 'example' => "/{$resource}?page[number]={$lastPage}"],
                            'prev' => ['type' => 'string', 'nullable' => true, 'example' => null],
                            'next' => ['type' => 'string', 'nullable' => true, 'example' => "/{$resource}?page[number]=2"],
                        ],
                    ],
                    'meta' => [
                        'type' => 'object',
                        'properties' => [
                            'total' => ['type' => 'integer', 'example' => $total],
                            'page' => ['type' => 'integer', 'example' => 1],
                            'per_page' => ['type' => 'integer', 'example' => 15],
                        ],
                    ],
                ],
                'example' => $this->buildCollectionExample($resource, $attributes, $total, $lastPage),
            ];
        }

        return [
            'type' => 'object',
            'required' => ['data'],
            'properties' => [
                'data' => $resourceSchema,
            ],
            'example' => [
                'data' => $this->examples->jsonApiResource($resource, $attributes),
            ],
        ];
    }

    private function buildResourceSchema(string $resource, ?array $attributes): array
    {
        $schema = [
            'type' => 'object',
            'required' => ['type', 'id'],
            'properties' => [
                'type' => ['type' => 'string', 'example' => $resource],
                'id' => ['type' => 'string', 'example' => '1'],
            ],
        ];

        if ($attributes) {
            $attrProps = [];
            foreach ($attributes as $name => $config) {
                $attrProps[$name] = [
                    'type' => $config['type'] ?? 'string',
                ];
                if (isset($config['description'])) {
                    $attrProps[$name]['description'] = $config['description'];
                }
            }

            $schema['properties']['attributes'] = [
                'type' => 'object',
                'properties' => $attrProps,
            ];
        } else {
            $schema['properties']['attributes'] = [
                'type' => 'object',
                'additionalProperties' => true,
            ];
        }

        $schema['properties']['links'] = [
            'type' => 'object',
            'properties' => [
                'self' => ['type' => 'string', 'example' => "/{$resource}/1"],
            ],
        ];

        return $schema;
    }

    private function buildCollectionExample(string $resource, ?array $attributes, int $total, int $lastPage): array
    {
        return [
            'data' => [
                $this->examples->jsonApiResource($resource, $attributes, '1'),
                $this->examples->jsonApiResource($resource, $attributes, '2'),
            ],
            'links' => [
                'self' => "/{$resource}?page[number]=1",
                'first' => "/{$resource}?page[number]=1",
                'last' => "/{$resource}?page[number]={$lastPage}",
                'prev' => null,
                'next' => "/{$resource}?page[number]=2",
            ],
            'meta' => [
                'total' => $total,
                'page' => 1,
                'per_page' => 15,
            ],
        ];
    }
}