<?php

namespace Jurager\Documentator\Builders;

use Illuminate\Database\Eloquent\Model;
use Jurager\Documentator\Support\ResourceAstReader;
use ReflectionClass;
use Throwable;

/**
 * Derives filter/sort/include query parameters straight from the Eloquent model
 * and JSON:API resource backing an endpoint, instead of requiring them to be
 * retyped by hand in every docblock — the surest way for documentation to
 * drift from the code that actually enforces it.
 *
 * filter/sort go through Jurager\Filterable\Concerns\HasFilterable's own public
 * contract — filterableFields()/sortableFields() — rather than reflecting on
 * its internal $filterable/$sortable properties directly; the trait owns that
 * shape and stays free to change it without this class following along.
 * A model that doesn't use the trait simply yields nothing to derive.
 *
 * include is decided the same way the app itself decides whether include=x
 * would actually work: Eloquent's own Model::isRelation(), the exact check
 * Jurager\Microservice\JsonApi\Concerns\WithEagerIncludes::validateRelationTree()
 * runs at request time — not a hand-rolled return-type check that could drift
 * from what that trait considers a relation.
 */
class AutoParameterBuilder
{
    public function __construct(
        private ResourceAstReader $astReader,
    ) {
    }

    /**
     * Build auto-derived filter/sort/include query params for a listing endpoint.
     * A param a docblock declares explicitly for the same name always wins over
     * one of these — callers should merge accordingly, not overwrite.
     *
     * @param  string|null  $resourceClass  Resource class backing the response, if resolved
     * @param  string|null  $modelClass  Eloquent model class backing the resource, if resolved
     * @return array<int, array{name: string, type: string, required: bool, description: string}>
     */
    public function build(?string $resourceClass, ?string $modelClass): array
    {
        $params = [];
        $model = $this->modelInstance($modelClass);

        if ($model) {
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

    /**
     * One @queryParam per filterable field the client couldn't otherwise guess:
     * relationship dot-paths and fields carrying the non-standard `tree` operator.
     * Plain own-attribute filters are left out — they're already discoverable
     * from the resource's own response shape.
     */
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

    /**
     * A single @queryParam sort listing every whitelisted sort field.
     */
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

    /**
     * A single @queryParam include listing every relation the resource declares
     * in toRelationships() that the model also recognizes via isRelation(). Both
     * must hold for `include=x` to actually surface `x` in the response — a
     * resource key with no matching model relation would fail validation, and a
     * model relation absent from toRelationships() never renders regardless.
     */
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

    /**
     * Instantiate the model the normal way — Eloquent's constructor never
     * touches the database, and running it is what boots the model's traits
     * (Model::bootIfNotBooted()). That boot step is what registers macro-based
     * relations declared via Relation::resolveRelationUsing() (jurager/eav's
     * HasAttributes registers attribute_values this way, for one); skipping
     * the constructor would leave isRelation() blind to those unless the
     * class happened to already be booted earlier in the same process.
     */
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
