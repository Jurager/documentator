<?php

namespace Jurager\Documentator\Parsers;

use Illuminate\Contracts\Container\Container;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use ReflectionMethod;
use ReflectionNamedType;
use ReflectionUnionType;
use Throwable;

class ValidationParser
{
    public function __construct(protected SourceParser $source, protected Container $container) {}

    public function parse(?ReflectionMethod $method, Route $route): array
    {
        if ($method === null) {
            return [];
        }

        foreach ($method->getParameters() as $parameter) {
            $type = $parameter->getType();
            $types = $type instanceof ReflectionUnionType ? $type->getTypes() : [$type];

            foreach ($types as $type) {
                if (! $type instanceof ReflectionNamedType || ! is_subclass_of($type->getName(), FormRequest::class)) {
                    continue;
                }

                $class = $type->getName();
                try {
                    $request = $class::createFrom(Request::create('/'.$route->uri(), $route->methods()[0]));
                    $request->setContainer($this->container);
                    $requestRoute = clone $route;
                    $requestRoute->bind($request);
                    $request->setRouteResolver(fn () => $requestRoute);

                    $rules = $this->container->call([$request, 'rules']);
                    if (is_array($rules)) {
                        return $rules;
                    }

                    $this->source->report($class, 'rules() must return an array.');
                } catch (Throwable $exception) {
                    $this->source->report($class, $exception->getMessage());
                }
            }
        }

        return $this->source->validationRules($method);
    }
}
