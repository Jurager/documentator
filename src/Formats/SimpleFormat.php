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
                    'status' => ['type' => 'string', 'enum' => ['success'], 'example' => 'success'],
                    'data' => ['oneOf' => [
                        ['type' => 'object', 'additionalProperties' => true],
                        ['type' => 'array', 'items' => ['type' => 'object']],
                    ]],
                ],
            ],
            'ErrorResponse' => [
                'type' => 'object',
                'properties' => [
                    'status' => ['type' => 'string', 'enum' => ['error'], 'example' => 'error'],
                    'messages' => ['type' => 'array', 'items' => ['type' => 'string']],
                ],
            ],
        ];
    }
}
