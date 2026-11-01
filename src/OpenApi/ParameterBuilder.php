<?php

namespace Jurager\Documentator\OpenApi;

use Illuminate\Support\Str;
use Jurager\Documentator\Routing\RouteInformation;

class ParameterBuilder
{
    public function __construct(protected TypeResolver $types, protected AutoParameterBuilder $automatic) {}

    public function build(RouteInformation $information, string $method, bool $examples = true): array
    {
        $parameters = $this->pathParameters($information, $examples);
        $automatic = $method === 'get'
            ? $this->automatic->build($information->resourceClass, $information->model, $information->isCollection($method))
            : [];

        $definitions = [
            'query' => [...($information->doc['queryParams'] ?? []), ...$automatic],
            'header' => $information->doc['headers'] ?? [],
        ];

        foreach ($definitions as $location => $items) {
            foreach ($items as $definition) {
                $parameter = $this->parameter($definition, $location);
                $name = $location === 'header' ? strtolower($parameter['name']) : $parameter['name'];
                $parameters[$location.':'.$name] ??= $parameter;
            }
        }

        return array_values($parameters);
    }

    protected function pathParameters(RouteInformation $information, bool $examples): array
    {
        $parameters = [];
        $definitions = collect($information->doc['urlParams'] ?? [])->keyBy('name');

        foreach ($information->route->parameterNames() as $name) {
            $definition = $definitions->get($name, []);
            $definition['name'] = $name;
            $definition['type'] ??= $information->bindings[$name] ?? 'string';
            $definition['description'] ??= __('documentator::documentator.id_of', ['name' => Str::headline($name)]);
            $parameter = $this->parameter($definition, 'path');
            $parameter['required'] = true;

            if (! array_key_exists('example', $parameter) && $examples) {
                $parameter['example'] = match ($parameter['schema']['type']) {
                    'integer', 'number' => 1,
                    'boolean' => true,
                    'array' => [],
                    'object' => (object) [],
                    default => 'string',
                };
            }

            $parameters['path:'.$name] = $parameter;
        }

        return $parameters;
    }

    protected function parameter(array $definition, string $location): array
    {
        $parameter = [
            'name' => $location === 'query' ? $this->queryName($definition['name']) : $definition['name'],
            'in' => $location,
            'required' => $definition['required'] ?? false,
            'schema' => $this->types->schema($definition['type']),
            'description' => $definition['description'] ?? '',
        ];

        if (array_key_exists('example', $definition)) {
            $parameter['example'] = $definition['example'];
        }

        return $parameter;
    }

    protected function queryName(string $name): string
    {
        $segments = explode('.', $name);
        $first = array_shift($segments);

        return $first.($segments ? '['.implode('][', $segments).']' : '');
    }
}
