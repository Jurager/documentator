<?php

namespace Jurager\Documentator\Routing;

use Illuminate\Contracts\Config\Repository;
use Illuminate\Routing\Route;
use Illuminate\Routing\Router;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class RouteCollector
{
    public function __construct(protected Router $router, protected Repository $config) {}

    /**
     * @param  array{include?: array, exclude?: array, exclude_middleware?: array, methods?: array}|null  $config
     * @return Collection<int, Route>
     */
    public function collect(?array $config = null): Collection
    {
        $config ??= $this->config->get('documentator.routes') ?? [];
        $methods = $config['methods'] ?? ['get', 'post', 'put', 'patch', 'delete'];

        return collect($this->router->getRoutes())
            ->filter(fn (Route $route) => $this->shouldInclude($route, $config) && $this->getAllowedMethods($route, $methods) !== [])
            ->values();
    }

    protected function shouldInclude(Route $route, array $config): bool
    {
        if (Str::is($config['exclude'] ?? [], $route->uri())) {
            return false;
        }

        foreach ($config['exclude_middleware'] ?? [] as $middleware) {
            if (in_array($middleware, $route->middleware(), true)) {
                return false;
            }
        }

        $includes = $config['include'] ?? [];

        return $includes === [] || Str::is($includes, $route->uri());
    }

    public function getAllowedMethods(Route $route, ?array $allowedMethods = null): array
    {
        $allowedMethods ??= $this->config->get('documentator.routes.methods') ?? ['get', 'post', 'put', 'patch', 'delete'];

        return array_values(array_intersect(
            array_map('strtolower', $route->methods()),
            array_map('strtolower', $allowedMethods),
            ['get', 'put', 'post', 'delete', 'options', 'head', 'patch', 'trace'],
        ));
    }

    public function normalizePath(Route $route): string
    {
        $path = preg_replace('/\{([^}]+)\?}/', '{$1}', $route->uri());

        return '/'.ltrim($path, '/');
    }
}
