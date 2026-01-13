<?php

namespace Jurager\Documentator\Formats;

use Jurager\Documentator\Support\ExampleGenerator;

abstract class AbstractFormat implements ResponseFormat
{
    protected ExampleGenerator $examples;

    public function __construct()
    {
        $this->examples = new ExampleGenerator;
    }

    abstract public function name(): string;

    abstract public function description(): string;

    abstract public function schemas(): array;

    abstract protected function successSchema(): string;

    abstract protected function errorSchema(): string;

    public function responses(): array
    {
        $success = ['$ref' => '#/components/schemas/'.$this->successSchema()];
        $error = ['$ref' => '#/components/schemas/'.$this->errorSchema()];

        return [
            'Success' => $this->wrapSchema(__('documentator::messages.success_response'), $success),
            'Created' => $this->wrapSchema(__('documentator::messages.created_response'), $success),
            'BadRequest' => $this->wrapSchema(__('documentator::messages.bad_request'), $error),
            'Unauthorized' => $this->wrapSchema(__('documentator::messages.unauthorized'), $error),
            'NotFound' => $this->wrapSchema(__('documentator::messages.not_found'), $error),
            'ValidationError' => $this->wrapSchema(__('documentator::messages.validation_error'), $error),
            'NoContent' => ['description' => __('documentator::messages.no_content')],
        ];
    }

    /**
     * Build operation response - override in child classes for format-specific responses.
     */
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
            'description' => 'Success',
            'content' => ['application/json' => ['schema' => ['$ref' => '#/components/schemas/'.$this->successSchema()]]],
        ]];
    }

    protected function wrapSchema(string $description, array $schema): array
    {
        return [
            'description' => $description,
            'content' => ['application/json' => ['schema' => $schema]],
        ];
    }
}