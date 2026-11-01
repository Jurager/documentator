<?php

namespace Jurager\Documentator;

use Closure;
use Illuminate\Contracts\Config\Repository;
use InvalidArgumentException;
use Jurager\Documentator\Contracts\ResponseFormat;
use Jurager\Documentator\OpenApi\Document;
use Jurager\Documentator\OpenApi\ExampleGenerator;
use Jurager\Documentator\OpenApi\OperationBuilder;
use Jurager\Documentator\Parsers\RouteParser;
use Jurager\Documentator\Routing\RouteCollector;

class Documentator
{
    protected int $processedCount = 0;

    protected array $warnings = [];

    public function __construct(
        protected RouteCollector $routes,
        protected RouteParser $parser,
        protected OperationBuilder $operations,
        protected ResponseFormat $format,
        protected ExampleGenerator $examples,
        protected Repository $config,
    ) {}

    public function generate(?ResponseFormat $format = null, ?Closure $progress = null): array
    {
        $this->reset();
        $settings = $this->exampleSettings();
        $format ??= $this->format;
        $document = $this->newDocument($format);

        foreach ($this->routes->collect() as $route) {
            $methods = $this->routes->getAllowedMethods($route);
            $path = $this->routes->normalizePath($route);
            $information = $this->parser->parse($route);

            foreach ($methods as $method) {
                $document->addOperation($path, $method, $this->operations->build($information, $method, $format));
            }

            $this->processedCount++;
            if ($progress !== null) {
                $progress($route, $path, $methods);
            }
        }

        $specification = $document->toArray();
        $tags = $this->tags($document->tags());
        if ($tags !== []) {
            $specification['tags'] = $tags;
        }

        $this->warnings = $this->parser->warnings();

        // Inspect every source before seeding Faker: application rules may consume random values.
        return ($settings['enabled'] ?? true)
            ? $this->examples->populate($specification, $document->requestSchemas(), $settings['seed'] ?? null)
            : $specification;
    }

    public function getProcessedCount(): int
    {
        return $this->processedCount;
    }

    public function warnings(): array
    {
        return $this->warnings;
    }

    protected function reset(): void
    {
        $this->processedCount = 0;
        $this->warnings = [];
        $this->parser->reset();
    }

    protected function newDocument(ResponseFormat $format): Document
    {
        $version = $this->config->get('documentator.openapi_version') ?? '3.0.3';
        if (! is_string($version) || ! preg_match('/^3\.0\.\d+$/', $version)) {
            throw new InvalidArgumentException('Documentator supports OpenAPI 3.0.x.');
        }

        $attributes = [
            'openapi' => $version,
            'info' => $this->info(),
            'servers' => $this->servers(),
            'components' => [
                'schemas' => $format->schemas() ?: (object) [],
                'responses' => $format->responses() ?: (object) [],
                'securitySchemes' => $this->config->get('documentator.security.schemes') ?: (object) [],
            ],
        ];

        $security = $this->config->get('documentator.security.default');
        if ($security !== null) {
            $attributes['security'] = $this->security($security);
        }

        return new Document($attributes);
    }

    protected function exampleSettings(): array
    {
        $settings = $this->config->get('documentator.examples') ?? [];
        if (! is_array($settings)) {
            throw new InvalidArgumentException('The examples setting must be an array.');
        }
        if (isset($settings['enabled']) && ! is_bool($settings['enabled'])) {
            throw new InvalidArgumentException('The examples.enabled setting must be a boolean.');
        }
        if (isset($settings['seed']) && ! is_int($settings['seed'])) {
            throw new InvalidArgumentException('The example seed must be an integer or null.');
        }

        return $settings;
    }

    protected function security(array $security): array
    {
        if ($security !== [] && array_is_list($security) && is_string($security[0])) {
            $security = [array_fill_keys($security, [])];
        } elseif ($security !== [] && ! array_is_list($security)) {
            $security = [$security];
        }

        return array_map(fn ($requirement) => $requirement ?: (object) [], $security);
    }

    protected function info(): array
    {
        $info = $this->config->get('documentator.info') ?? [];

        return array_filter([
            'title' => $info['title'] ?? $this->config->get('app.name', 'API'),
            'version' => $info['version'] ?? '1.0.0',
            'description' => $info['description'] ?? null,
            'contact' => array_filter($info['contact'] ?? []),
            'license' => array_filter($info['license'] ?? []),
        ], fn ($value) => $value !== null && $value !== '' && $value !== []);
    }

    protected function servers(): array
    {
        $servers = $this->config->get('documentator.servers') ?? [['url' => $this->config->get('app.url', 'http://localhost')]];

        return array_map(function (array $server) {
            $url = rtrim($server['url'], '/');
            $server['url'] = $url === '' ? '/' : $url;

            return $server;
        }, $servers);
    }

    protected function tags(array $used): array
    {
        $config = $this->config->get('documentator.tags') ?? [];
        $tags = [];

        foreach ($config['definitions'] ?? [] as $name => $description) {
            $tags[$name] = compact('name', 'description');
        }
        if ($config['auto_generate'] ?? true) {
            foreach ($used as $name) {
                $tags[$name] ??= ['name' => $name];
            }
        }
        if ($config['sort'] ?? true) {
            ksort($tags);
        }

        return array_values($tags);
    }
}
