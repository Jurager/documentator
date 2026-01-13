<?php

namespace Jurager\Documentator\Formats;

class SimpleFormat extends AbstractFormat
{
    public function name(): string
    {
        return 'simple';
    }

    public function description(): string
    {
        return 'REST API';
    }

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
                    'success' => ['type' => 'boolean', 'example' => true],
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
                    'success' => ['type' => 'boolean', 'example' => false],
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

        $schema = [
            'type' => 'object',
            'properties' => [
                'success' => ['type' => 'boolean', 'example' => true],
            ],
        ];

        if ($isCollection) {
            $schema['properties']['data'] = [
                'type' => 'array',
                'items' => ['type' => 'object'],
                'example' => [
                    $this->examples->object($attributes, '1'),
                    $this->examples->object($attributes, '2'),
                ],
            ];
            $schema['properties']['meta'] = [
                'type' => 'object',
                'example' => [
                    'total' => $this->examples->faker()->numberBetween(50, 500),
                    'page' => 1,
                    'per_page' => 15,
                ],
            ];
        } else {
            $schema['properties']['data'] = [
                'type' => 'object',
                'example' => $this->examples->object($attributes),
            ];
        }

        return [$status => [
            'description' => match ($status) {
                '201' => 'Created',
                default => 'Success',
            },
            'content' => ['application/json' => ['schema' => $schema]],
        ]];
    }
}