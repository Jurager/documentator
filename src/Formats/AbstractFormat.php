<?php

namespace Jurager\Documentator\Formats;

use Jurager\Documentator\Builders\SchemaBuilder;

abstract class AbstractFormat implements AbstractFormatInterface
{
    protected SchemaBuilder $examples;

    public function __construct(?SchemaBuilder $examples = null)
    {
        $this->examples = $examples ?? new SchemaBuilder();
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

    /**
     * Get attributes from responseData or return defaults.
     */
    protected function getAttributes(?array $responseData): array
    {
        if ($responseData && ! empty($responseData['attributes'])) {
            return $responseData['attributes'];
        }

        return [
            'created_at' => ['type' => 'string'],
            'updated_at' => ['type' => 'string'],
        ];
    }

    /**
     * Get relationships from responseData.
     */
    protected function getRelationships(?array $responseData): array
    {
        return $responseData['relationships'] ?? [];
    }
}
