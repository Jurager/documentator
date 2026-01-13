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
        return 'API следует спецификации JSON:API';
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
                    'type' => ['type' => 'string', 'example' => 'users'],
                    'id' => ['type' => 'string', 'example' => '1'],
                    'attributes' => ['type' => 'object', 'additionalProperties' => true],
                    'relationships' => ['type' => 'object', 'additionalProperties' => true],
                    'links' => ['type' => 'object', 'additionalProperties' => true],
                    'meta' => ['type' => 'object', 'additionalProperties' => true],
                ],
            ],
            'JsonApiDocument' => [
                'type' => 'object',
                'properties' => [
                    'data' => ['oneOf' => [
                        ['$ref' => '#/components/schemas/JsonApiResource'],
                        ['type' => 'array', 'items' => ['$ref' => '#/components/schemas/JsonApiResource']],
                    ]],
                    'included' => ['type' => 'array', 'items' => ['$ref' => '#/components/schemas/JsonApiResource']],
                    'links' => ['type' => 'object', 'additionalProperties' => true],
                    'meta' => ['type' => 'object', 'additionalProperties' => true],
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
                                'status' => ['type' => 'string'],
                                'code' => ['type' => 'string'],
                                'title' => ['type' => 'string'],
                                'detail' => ['type' => 'string'],
                                'source' => [
                                    'type' => 'object',
                                    'properties' => [
                                        'pointer' => ['type' => 'string'],
                                        'parameter' => ['type' => 'string'],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];
    }
}
