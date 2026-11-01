<?php

namespace Jurager\Documentator\Parsers;

use Illuminate\Contracts\Config\Repository;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Routing\Route;
use Illuminate\Support\Str;
use Jurager\Documentator\Routing\RouteInformation;
use ReflectionMethod;
use ReflectionNamedType;
use Throwable;

class RouteParser
{
    public function __construct(
        protected PhpDocParser $documentation,
        protected ValidationParser $validation,
        protected ResourceParser $resources,
        protected SourceParser $source,
        protected Repository $config,
    ) {}

    public function reset(): void
    {
        $this->source->reset();
        $this->resources->reset();
    }

    public function warnings(): array
    {
        return $this->source->warnings();
    }

    public function parse(Route $route, bool $withValidation = true): RouteInformation
    {
        $information = new RouteInformation($route);
        $method = $this->controllerMethod($route);
        $information->doc = $this->documentation->parse($method?->getDocComment() ?: '');
        $information->resource = $information->doc['resource'] ?? $information->resource;
        $namespaces = $this->config->get('documentator.resources.namespaces') ?? ['App\Http\Resources', 'App\Models'];

        $this->resolveBindings($information, $method);
        $information->model ??= $this->resources->model($this->resources->guessModelClass($information->resource, $namespaces));
        $information->resourceClass = $this->resources->find($method, $information->resource, $namespaces);
        $information->data = $this->resources->parse($information->resourceClass, $information->model);

        if ($withValidation) {
            $information->doc['validation'] = $this->validation->parse($method, $route);
        }

        return $information;
    }

    protected function resolveBindings(RouteInformation $information, ?ReflectionMethod $method): void
    {
        foreach ($method?->getParameters() ?? [] as $parameter) {
            $type = $parameter->getType();
            if (! $type instanceof ReflectionNamedType || ! is_subclass_of($type->getName(), Model::class)) {
                continue;
            }

            foreach ($information->route->parameterNames() as $name) {
                if ($parameter->getName() !== Str::camel($name)) {
                    continue;
                }

                $model = $this->resources->model($type->getName());
                if ($model === null) {
                    continue;
                }

                $field = $information->route->bindingFieldFor($name) ?? $model->getRouteKeyName();
                $information->bindings[$name] = $field === $model->getKeyName() && $model->getKeyType() === 'int' ? 'integer' : 'string';
                $information->model = $model;
            }
        }
    }

    protected function controllerMethod(Route $route): ?ReflectionMethod
    {
        $class = $route->getControllerClass();
        if ($class === null) {
            return null;
        }

        try {
            return new ReflectionMethod($class, $route->getActionMethod());
        } catch (Throwable $exception) {
            $this->source->report($route->getActionName(), $exception->getMessage());

            return null;
        }
    }
}
