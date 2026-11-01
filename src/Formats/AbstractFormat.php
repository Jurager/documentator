<?php

namespace Jurager\Documentator\Formats;

use Jurager\Documentator\Contracts\ResponseFormat;

abstract class AbstractFormat implements ResponseFormat
{
    protected string $mediaType = 'application/json';

    abstract protected function successSchema(): string;

    abstract protected function errorSchema(): string;

    public function responses(): array
    {
        $success = ['$ref' => '#/components/schemas/'.$this->successSchema()];
        $error = ['$ref' => '#/components/schemas/'.$this->errorSchema()];

        return [
            'Success' => $this->wrapSchema(__('documentator::documentator.success_response'), $success),
            'Created' => $this->wrapSchema(__('documentator::documentator.created_response'), $success),
            'BadRequest' => $this->wrapSchema(__('documentator::documentator.bad_request'), $error),
            'Unauthorized' => $this->wrapSchema(__('documentator::documentator.unauthorized'), $error),
            'NotFound' => $this->wrapSchema(__('documentator::documentator.not_found'), $error),
            'ValidationError' => $this->wrapSchema(__('documentator::documentator.validation_error'), $error),
            'NoContent' => ['description' => __('documentator::documentator.no_content')],
        ];
    }

    protected function wrapSchema(string $description, array $schema): array
    {
        return [
            'description' => $description,
            'content' => [$this->mediaType => ['schema' => $schema]],
        ];
    }

    protected function getAttributes(array $responseData): array
    {
        $attributes = $responseData['attributes'] ?? [];

        foreach ($attributes as $name => $schema) {
            if (($schema['type'] ?? null) === 'array') {
                $attributes[$name]['items'] ??= (object) [];
            }
        }

        return $attributes;
    }
}
