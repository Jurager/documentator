<?php

namespace Jurager\Documentator\OpenApi;

use Illuminate\Database\Eloquent\Model;
use Jurager\Documentator\Parsers\SourceParser;
use ReflectionClass;
use Throwable;

class AutoParameterBuilder
{
    public function __construct(protected SourceParser $source) {}

    public function build(?string $resourceClass, ?Model $model, bool $isCollection = true): array
    {
        $parameters = [];

        if ($model && $isCollection) {
            $parameters = array_merge($parameters, $this->filterParams($model));

            if ($sort = $this->sortParam($model)) {
                $parameters[] = $sort;
            }
        }

        if ($model && $resourceClass && class_exists($resourceClass)) {
            if ($include = $this->includeParam($resourceClass, $model)) {
                $parameters[] = $include;
            }
        }

        return $parameters;
    }

    protected function filterParams(Model $model): array
    {
        $parameters = [];

        foreach ($this->modelFields($model, 'filterableFields') as $field => $operators) {
            if (is_int($field) || ! is_array($operators) || empty($operators)) {
                continue;
            }

            $isRelation = str_contains($field, '.');
            $hasTree = in_array('tree', $operators, true);

            if (! $isRelation && ! $hasTree) {
                continue;
            }

            $parameters[] = [
                'name' => "filter.$field",
                'type' => 'string',
                'required' => false,
                'description' => __('documentator::documentator.filter_operators', [
                    'operators' => implode(', ', array_map(fn ($operator) => "`{$operator}`", $operators)),
                ]),
            ];
        }

        return $parameters;
    }

    protected function sortParam(Model $model): ?array
    {
        $sortable = array_values($this->modelFields($model, 'sortableFields'));

        if (empty($sortable)) {
            return null;
        }

        return [
            'name' => 'sort',
            'type' => 'string',
            'required' => false,
            'description' => __('documentator::documentator.sortable_fields', [
                'fields' => implode(', ', array_map(fn ($field) => "`{$field}`", $sortable)),
            ]),
        ];
    }

    protected function includeParam(string $resourceClass, Model $model): ?array
    {
        $resourceRef = new ReflectionClass($resourceClass);

        if (! $resourceRef->hasMethod('toRelationships') || ! $resourceRef->getFileName()) {
            return null;
        }

        $method = $resourceRef->getMethod('toRelationships');
        $declared = $this->source->arrayKeys($method);
        $valid = array_values(array_filter($declared, fn ($name) => $model->isRelation($name)));

        if (empty($valid)) {
            return null;
        }

        return [
            'name' => 'include',
            'type' => 'string',
            'required' => false,
            'description' => __('documentator::documentator.available_includes', [
                'relations' => implode(', ', $valid),
            ]),
        ];
    }

    protected function modelFields(Model $model, string $method): array
    {
        if (! method_exists($model, $method)) {
            return [];
        }

        try {
            $fields = $model->$method();
            if (is_array($fields)) {
                return $fields;
            }

            $this->source->report($model::class.'::'.$method, 'Expected an array of fields.');
        } catch (Throwable $exception) {
            $this->source->report($model::class.'::'.$method, $exception->getMessage());
        }

        return [];
    }
}
