<?php

namespace Jurager\Documentator\Linters;

use Jurager\Documentator\Builders\SpecificationBuilder;
use Jurager\Documentator\Collectors\RouteCollector;

/**
 * Detects query/header parameters that are documented with a different type
 * or description depending on the endpoint. A preset (see config('documentator.presets'))
 * eliminates drift by construction; this linter catches everything a preset doesn't cover.
 */
class ParameterConsistencyLinter
{
    /**
     * @param  array  $config  The 'documentator' config array
     */
    public function __construct(private array $config)
    {
    }

    /**
     * Run the check and return every inconsistently-documented parameter.
     *
     * @return array<int, array{in: string, name: string, variants: array<int, array{signature: array{type: ?string, description: ?string}, locations: array<int, string>}>}>
     */
    public function lint(): array
    {
        $builder = new SpecificationBuilder(new RouteCollector(), $this->config);
        $spec = $builder->build();

        $seen = $this->collectSignatures($spec['paths'] ?? []);

        return $this->onlyInconsistent($seen);
    }

    /**
     * Group every query/header parameter occurrence by name, keeping each
     * distinct (type, description) signature and where it was seen.
     *
     * @param  array|\stdClass  $paths  The 'paths' section of the built specification
     * @return array<string, array{in: string, name: string, variants: array<string, array{signature: array{type: ?string, description: ?string}, locations: array<int, string>}>}>
     */
    private function collectSignatures(array|\stdClass $paths): array
    {
        $seen = [];

        foreach ((array) $paths as $path => $operations) {
            foreach ($operations as $method => $operation) {
                foreach ($operation['parameters'] ?? [] as $param) {
                    if (! in_array($param['in'], ['query', 'header'], true)) {
                        continue;
                    }

                    $key = $param['in'].':'.$param['name'];
                    $signature = [
                        'type' => $param['schema']['type'] ?? null,
                        'description' => $param['description'] ?? null,
                    ];
                    $signatureKey = md5(serialize($signature));
                    $location = strtoupper($method).' '.$path;

                    $seen[$key] ??= ['in' => $param['in'], 'name' => $param['name'], 'variants' => []];
                    $seen[$key]['variants'][$signatureKey] ??= ['signature' => $signature, 'locations' => []];
                    $seen[$key]['variants'][$signatureKey]['locations'][] = $location;
                }
            }
        }

        return $seen;
    }

    /**
     * Keep only parameters that were documented with more than one signature.
     *
     * @param  array  $seen  Output of collectSignatures()
     * @return array<int, array{in: string, name: string, variants: array<int, array{signature: array{type: ?string, description: ?string}, locations: array<int, string>}>}>
     */
    private function onlyInconsistent(array $seen): array
    {
        $issues = [];

        foreach ($seen as $entry) {
            if (count($entry['variants']) < 2) {
                continue;
            }

            $entry['variants'] = array_values($entry['variants']);
            $issues[] = $entry;
        }

        usort($issues, fn ($a, $b) => [$a['in'], $a['name']] <=> [$b['in'], $b['name']]);

        return $issues;
    }
}
