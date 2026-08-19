<?php

namespace Jurager\Documentator\Builders;

use Illuminate\Database\Eloquent\Model;
use Jurager\Documentator\Support\ResourceAstReader;
use ReflectionClass;
use Throwable;

class AutoParameterBuilder
{
    public function __construct(
        private ResourceAstReader $astReader,
    ) {
    }

    public function build(?string $resourceClass, ?string $modelClass, bool $isCollection = true): array
    {
        $params = [];
        $model = $this->modelInstance($modelClass);

        if ($model && $isCollection) {
            $params = array_merge($params, $this->filterParams($model));

            if ($sort = $this->sortParam($model)) {
                $params[] = $sort;
            }
        }

        if ($model && $resourceClass && class_exists($resourceClass)) {
            if ($include = $this->includeParam($resourceClass, $model)) {
                $params[] = $include;
            }
        }

        return $params;
    }

    private function filterParams(Model $model): array
    {
        if (! method_exists($model, 'filterableFields')) {
            return [];
        }

        $params = [];

        foreach ($model->filterableFields() as $field => $operators) {
            if (is_int($field) || ! is_array($operators) || empty($operators)) {
                continue; // shorthand "field" (implicit eq) or a non-array shorthand like 'boolean'
            }

            $isRelation = str_contains($field, '.');
            $hasTree = in_array('tree', $operators, true);

            if (! $isRelation && ! $hasTree) {
                continue;
            }

            $params[] = [
                'name' => "filter.$field",
                'type' => 'string',
                'required' => false,
                'description' => __('documentator::documentator.filter_operators', [
                    'operators' => implode(', ', array_map(fn ($op) => "`$op`", $operators)),
                ]),
            ];
        }

        return $params;
    }

    private function sortParam(Model $model): ?array
    {
        if (! method_exists($model, 'sortableFields')) {
            return null;
        }

        $sortable = array_values($model->sortableFields());

        if (empty($sortable)) {
            return null;
        }

        return [
            'name' => 'sort',
            'type' => 'string',
            'required' => false,
            'description' => __('documentator::documentator.sortable_fields', [
                'fields' => implode(', ', array_map(fn ($f) => "`$f`", $sortable)),
            ]),
        ];
    }

    private function includeParam(string $resourceClass, Model $model): ?array
    {
        try {
            $resourceRef = new ReflectionClass($resourceClass);
        } catch (Throwable) {
            return null;
        }

        if (! $resourceRef->hasMethod('toRelationships') || ! $resourceRef->getFileName()) {
            return null;
        }

        $declared = $this->astReader->arrayKeys($resourceRef->getFileName(), 'toRelationships');
        $valid = array_values(array_filter(
            $declared,
            fn ($name) => $model->isRelation($name)
        ));

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

    private function modelInstance(?string $modelClass): ?Model
    {
        if (! $modelClass || ! class_exists($modelClass)) {
            return null;
        }

        try {
            $ref = new ReflectionClass($modelClass);

            if (! $ref->isSubclassOf(Model::class)) {
                return null;
            }

            return $ref->newInstance();
        } catch (Throwable) {
            return null;
        }
    }
}
