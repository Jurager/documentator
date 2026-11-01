<?php

namespace Jurager\Documentator\Parsers;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Http\Resources\Json\ResourceCollection;
use Illuminate\Support\Str;
use Jurager\Documentator\OpenApi\TypeResolver;
use ReflectionClass;
use ReflectionMethod;
use ReflectionNamedType;
use Throwable;

class ResourceParser
{
    protected array $resources = [];

    protected array $models = [];

    public function __construct(protected SourceParser $source, protected TypeResolver $types) {}

    public function reset(): void
    {
        $this->resources = [];
        $this->models = [];
    }

    public function find(?ReflectionMethod $method, string $resource, array $namespaces): ?string
    {
        if ($method !== null) {
            $returnType = $method->getReturnType();
            if ($returnType instanceof ReflectionNamedType && $this->isResource($returnType->getName())) {
                return $returnType->getName();
            }

            $class = $this->source->resourceClass($method);
            if ($class !== null && $this->isResource($class)) {
                return $class;
            }
        }

        return $this->guessResourceClass($resource, $namespaces);
    }

    public function parse(?string $class, ?Model $model = null): array
    {
        if ($class === null || ! class_exists($class)) {
            return [];
        }

        $key = $class.':'.($model ? $model::class : '');
        if (isset($this->resources[$key])) {
            return $this->resources[$key];
        }

        try {
            $reflection = new ReflectionClass($class);
            $attributes = [];
            $defaults = $reflection->getDefaultProperties();
            if ($reflection->hasProperty('attributes') && $reflection->getProperty('attributes')->isPublic()) {
                foreach ($defaults['attributes'] ?? [] as $attribute) {
                    if (is_string($attribute)) {
                        $attributes[$attribute] = isset($model?->getCasts()[$attribute])
                            ? $this->types->fromCast($model->getCasts()[$attribute])
                            : $this->types->schema($this->types->fromFieldName($attribute));
                    }
                }
            }

            if ($reflection->hasMethod('toArray')) {
                $attributes = array_replace($attributes, $this->source->properties($reflection->getMethod('toArray'), $model));
            }

            $relationships = [];
            if ($reflection->hasMethod('toRelationships')) {
                $method = $reflection->getMethod('toRelationships');
                $links = $this->source->relationLinks($method);
                foreach ($links as $name => $link) {
                    $relationships[$name] = [
                        'type' => Str::snake($name),
                        'resource' => $link['resource'],
                        'collection' => $link['collection'],
                    ];
                }
            }

            return $this->resources[$key] = compact('attributes', 'relationships');
        } catch (Throwable $exception) {
            $this->source->report($class, $exception->getMessage());

            return [];
        }
    }

    public function guessResourceClass(string $resource, array $namespaces = []): ?string
    {
        $name = Str::studly(Str::singular($resource));
        foreach (array_unique([...$namespaces, 'App\Http\Resources', "App\\Http\\Resources\\{$name}"]) as $namespace) {
            $class = rtrim($namespace, '\\')."\\{$name}Resource";
            if ($this->isResource($class)) {
                return $class;
            }
        }

        return null;
    }

    public function guessModelClass(string $resource, array $namespaces = ['App\Models']): ?string
    {
        $name = Str::studly(Str::singular($resource));
        foreach ($namespaces as $namespace) {
            $class = rtrim($namespace, '\\')."\\{$name}";
            if (is_subclass_of($class, Model::class)) {
                return $class;
            }
        }

        return null;
    }

    public function model(?string $class): ?Model
    {
        if ($class === null || ! is_subclass_of($class, Model::class)) {
            return null;
        }

        if (! array_key_exists($class, $this->models)) {
            try {
                $this->models[$class] = new $class;
            } catch (Throwable $exception) {
                $this->source->report($class, $exception->getMessage());
                $this->models[$class] = null;
            }
        }

        return $this->models[$class];
    }

    protected function isResource(string $class): bool
    {
        return is_subclass_of($class, JsonResource::class) && ! is_a($class, ResourceCollection::class, true);
    }
}
