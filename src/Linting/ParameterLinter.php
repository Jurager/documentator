<?php

namespace Jurager\Documentator\Linting;

use Jurager\Documentator\OpenApi\ParameterBuilder;
use Jurager\Documentator\Parsers\RouteParser;
use Jurager\Documentator\Routing\RouteCollector;

class ParameterLinter
{
    protected array $warnings = [];

    public function __construct(
        protected RouteCollector $routes,
        protected RouteParser $parser,
        protected ParameterBuilder $parameters,
    ) {}

    public function lint(): array
    {
        $this->parser->reset();
        $this->warnings = [];
        $seen = [];

        foreach ($this->routes->collect() as $route) {
            $methods = $this->routes->getAllowedMethods($route);
            $information = $this->parser->parse($route, withValidation: false);
            $path = $this->routes->normalizePath($route);
            foreach ($methods as $method) {
                foreach ($this->parameters->build($information, $method, examples: false) as $parameter) {
                    if (! in_array($parameter['in'], ['query', 'header'], true)) {
                        continue;
                    }

                    $name = $parameter['in'] === 'header' ? strtolower($parameter['name']) : $parameter['name'];
                    $key = $parameter['in'].':'.$name;
                    $signature = [
                        'type' => $parameter['schema']['type'] ?? null,
                        'description' => $parameter['description'] ?? '',
                        'schema' => $parameter['schema'],
                    ];
                    $hash = serialize($signature);
                    $seen[$key] ??= ['in' => $parameter['in'], 'name' => $parameter['name'], 'variants' => []];
                    $seen[$key]['variants'][$hash] ??= ['signature' => $signature, 'locations' => []];
                    $seen[$key]['variants'][$hash]['locations'][] = strtoupper($method)." {$path}";
                }
            }
        }

        $issues = [];
        foreach ($seen as $entry) {
            if (count($entry['variants']) > 1) {
                $entry['variants'] = array_values($entry['variants']);
                $issues[] = $entry;
            }
        }
        usort($issues, fn ($first, $second) => [$first['in'], $first['name']] <=> [$second['in'], $second['name']]);

        $this->warnings = $this->parser->warnings();

        return $issues;
    }

    public function warnings(): array
    {
        return $this->warnings;
    }
}
