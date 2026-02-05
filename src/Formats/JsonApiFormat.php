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

    public function operationResponse(string $method, string $resource, ?array $responseData = null, bool $isCollection = false): array
    {
        $status = match ($method) {
            'post' => '201',
            'delete' => '204',
            default => '200',
        };

        if ($status === '204') {
            return [$status => ['description' => 'No Content']];
        }

        $attributes = $this->getAttributes($responseData);
        $relationships = $this->getRelationships($responseData);

        return [$status => [
            'description' => match ($status) {
                '201' => 'Resource created',
                default => 'Success',
            },
            'content' => [
                'application/vnd.api+json' => [
                    'schema' => $this->buildResponseSchema($resource, $attributes, $relationships, $isCollection),
                ],
            ],
        ]];
    }

    private function buildResponseSchema(string $resource, array $attributes, array $relationships, bool $isCollection): array
    {
        $resourceSchema = $this->buildResourceSchema($resource, $attributes, $relationships);

        if ($isCollection) {
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
                            'self' => ['type' => 'string'],
                            'first' => ['type' => 'string'],
                            'last' => ['type' => 'string'],
                            'prev' => ['type' => 'string', 'nullable' => true],
                            'next' => ['type' => 'string', 'nullable' => true],
                        ],
                    ],
                    'meta' => [
                        'type' => 'object',
                        'properties' => [
                            'total' => ['type' => 'integer'],
                            'page' => ['type' => 'integer'],
                            'per_page' => ['type' => 'integer'],
                        ],
                    ],
                ],
                'example' => $this->buildCollectionExample($resource, $attributes, $relationships),
            ];
        }

        return [
            'type' => 'object',
            'required' => ['data'],
            'properties' => [
                'data' => $resourceSchema,
            ],
            'example' => [
                'data' => $this->buildResourceExample($resource, $attributes, $relationships, 1),
            ],
        ];
    }

    private function buildResourceSchema(string $resource, array $attributes, array $relationships): array
    {
        // Build attributes properties
        $attrProps = [];
        foreach ($attributes as $name => $config) {
            if ($name === 'id') {
                continue;
            }
            $attrProps[$name] = ['type' => $config['type'] ?? 'string'];
        }

        $schema = [
            'type' => 'object',
            'required' => ['type', 'id'],
            'properties' => [
                'type' => ['type' => 'string', 'example' => $resource],
                'id' => ['type' => 'string', 'example' => '1'],
                'attributes' => [
                    'type' => 'object',
                    'properties' => $attrProps ?: null,
                ],
                'links' => [
                    'type' => 'object',
                    'properties' => [
                        'self' => ['type' => 'string', 'example' => "/$resource/1"],
                    ],
                ],
            ],
        ];

        // Add relationships if present
        if (! empty($relationships)) {
            $relProps = [];
            foreach ($relationships as $name => $rel) {
                $relProps[$name] = [
                    'type' => 'object',
                    'properties' => [
                        'data' => $rel['collection']
                            ? ['type' => 'array', 'items' => $this->buildRelationshipLinkage($rel['type'])]
                            : $this->buildRelationshipLinkage($rel['type']),
                        'links' => [
                            'type' => 'object',
                            'properties' => [
                                'related' => ['type' => 'string'],
                            ],
                        ],
                    ],
                ];
            }

            $schema['properties']['relationships'] = [
                'type' => 'object',
                'properties' => $relProps,
            ];
        }

        return array_filter($schema, fn ($v) => $v !== null);
    }

    private function buildRelationshipLinkage(string $type): array
    {
        return [
            'type' => 'object',
            'required' => ['type', 'id'],
            'properties' => [
                'type' => ['type' => 'string', 'example' => $type],
                'id' => ['type' => 'string', 'example' => '1'],
            ],
        ];
    }

    private function buildResourceExample(string $resource, array $attributes, array $relationships, int $id): array
    {
        $example = $this->examples->generateJsonApiResource($resource, $attributes, $id);

        // Add relationships to example
        if (! empty($relationships)) {
            $example['relationships'] = [];

            foreach ($relationships as $name => $rel) {
                $relType = $rel['type'];

                if ($rel['collection']) {
                    $example['relationships'][$name] = [
                        'data' => [
                            ['type' => $relType, 'id' => '1'],
                            ['type' => $relType, 'id' => '2'],
                        ],
                        'links' => [
                            'related' => "/$resource/$id/$name",
                        ],
                    ];
                } else {
                    $example['relationships'][$name] = [
                        'data' => ['type' => $relType, 'id' => '1'],
                        'links' => [
                            'related' => "/$resource/$id/$name",
                        ],
                    ];
                }
            }
        }

        return $example;
    }

    private function buildCollectionExample(string $resource, array $attributes, array $relationships): array
    {
        return [
            'data' => [
                $this->buildResourceExample($resource, $attributes, $relationships, 1),
                $this->buildResourceExample($resource, $attributes, $relationships, 2),
            ],
            'links' => [
                'self' => "/$resource?page[number]=1",
                'first' => "/$resource?page[number]=1",
                'last' => "/$resource?page[number]=7",
                'prev' => null,
                'next' => "/$resource?page[number]=2",
            ],
            'meta' => [
                'total' => 100,
                'page' => 1,
                'per_page' => 15,
            ],
        ];
    }
}
