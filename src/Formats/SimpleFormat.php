<?php

namespace Jurager\Documentator\Formats;

class SimpleFormat extends AbstractFormat
{
    protected function successSchema(): string
    {
        return 'SuccessResponse';
    }

    protected function errorSchema(): string
    {
        return 'ErrorResponse';
    }

    public function schemas(): array
    {
        return [
            'SuccessResponse' => [
                'type' => 'object',
                'properties' => [
                    'success' => ['type' => 'boolean', 'enum' => [true]],
                    'data' => ['oneOf' => [
                        ['type' => 'object', 'additionalProperties' => true],
                        ['type' => 'array', 'items' => ['type' => 'object']],
                    ]],
                    'meta' => ['type' => 'object', 'properties' => [
                        'total' => ['type' => 'integer'],
                        'page' => ['type' => 'integer'],
                        'per_page' => ['type' => 'integer'],
                    ]],
                ],
            ],
            'ErrorResponse' => [
                'type' => 'object',
                'properties' => [
                    'success' => ['type' => 'boolean', 'enum' => [false]],
                    'message' => ['type' => 'string'],
                    'errors' => [
                        'type' => 'object',
                        'additionalProperties' => [
                            'type' => 'array',
                            'items' => ['type' => 'string'],
                        ],
                    ],
                ],
            ],
        ];
    }

    public function response(string $resource, array $data = [], bool $collection = false): array
    {
        $properties = $this->getAttributes($data);
        $item = ['type' => 'object', 'properties' => $properties ?: (object) []];
        $schema = [
            'type' => 'object',
            'properties' => [
                'success' => ['type' => 'boolean', 'enum' => [true]],
                'data' => $collection ? ['type' => 'array', 'items' => $item] : $item,
            ],
        ];
        if ($collection) {
            $schema['properties']['meta'] = [
                'type' => 'object',
                'properties' => [
                    'total' => ['type' => 'integer', 'minimum' => 0],
                    'page' => ['type' => 'integer', 'minimum' => 1],
                    'per_page' => ['type' => 'integer', 'minimum' => 1],
                ],
            ];
        }

        return ['content' => [$this->mediaType => ['schema' => $schema]]];
    }
}
