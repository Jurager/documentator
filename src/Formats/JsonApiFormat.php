<?php

namespace Jurager\Documentator\Formats;

class JsonApiFormat extends AbstractFormat
{
    protected string $mediaType = 'application/vnd.api+json';

    public function response(string $resource, array $data = [], bool $collection = false): array
    {
        $schema = $this->documentSchema($resource, $this->getAttributes($data), $data['relationships'] ?? [], $collection);

        return ['content' => [$this->mediaType => ['schema' => $schema]]];
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
                        ['type' => 'object', 'nullable' => true, 'enum' => [null]],
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

    protected function documentSchema(string $resource, array $attributes, array $relationships, bool $collection): array
    {
        $item = $this->resourceSchema($resource, $attributes, $relationships);
        $schema = [
            'type' => 'object',
            'required' => ['data'],
            'properties' => [
                'data' => $collection ? ['type' => 'array', 'items' => $item] : $item,
            ],
        ];

        if ($collection) {
            $schema['properties']['links'] = [
                'type' => 'object',
                'properties' => [
                    'self' => ['type' => 'string'],
                    'first' => ['type' => 'string'],
                    'last' => ['type' => 'string'],
                    'prev' => ['type' => 'string', 'nullable' => true],
                    'next' => ['type' => 'string', 'nullable' => true],
                ],
            ];
            $schema['properties']['meta'] = [
                'type' => 'object',
                'properties' => [
                    'total' => ['type' => 'integer'],
                    'page' => ['type' => 'integer'],
                    'per_page' => ['type' => 'integer'],
                ],
            ];
        }

        return $schema;
    }

    protected function resourceSchema(string $resource, array $attributes, array $relationships): array
    {
        unset($attributes['id']);
        $schema = $this->identifierSchema($resource);
        $schema['properties']['attributes'] = ['type' => 'object', 'properties' => $attributes ?: (object) []];
        $schema['properties']['links'] = ['type' => 'object', 'properties' => ['self' => ['type' => 'string']]];

        if ($relationships !== []) {
            $schema['properties']['relationships'] = $this->relationshipsSchema($relationships);
        }

        return $schema;
    }

    protected function relationshipsSchema(array $relationships): array
    {
        $properties = [];

        foreach ($relationships as $name => $relationship) {
            $identifier = $this->identifierSchema($relationship['type']);
            $properties[$name] = [
                'type' => 'object',
                'properties' => [
                    'data' => $relationship['collection'] ? ['type' => 'array', 'items' => $identifier] : $identifier,
                    'links' => ['type' => 'object', 'properties' => ['related' => ['type' => 'string']]],
                ],
            ];
        }

        return ['type' => 'object', 'properties' => $properties];
    }

    protected function identifierSchema(string $type): array
    {
        return [
            'type' => 'object',
            'required' => ['type', 'id'],
            'properties' => [
                'type' => ['type' => 'string', 'enum' => [$type]],
                'id' => ['type' => 'string'],
            ],
        ];
    }
}
