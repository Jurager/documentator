<?php

namespace Jurager\Documentator\Collectors;

use Illuminate\Routing\Route;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Route as RouteFacade;
use Illuminate\Support\Str;

class RouteCollector
{
    /**
     * Collect routes matching the given criteria.
     *
     * @param  array{include?: array, exclude?: array, exclude_middleware?: array}  $config
     * @return Collection<int, Route>
     */
    public function collect(array $config = []): Collection
    {
        return collect(RouteFacade::getRoutes())
            ->filter(fn (Route $route) => $this->shouldInclude($route, $config))
            ->values();
    }

    /**
     * Check if route should be included based on configuration.
     */
    private function shouldInclude(Route $route, array $config): bool
    {
        $uri = $route->uri();

        // Check exclude patterns
        foreach ($config['exclude'] ?? [] as $pattern) {
            if (Str::is($pattern, $uri)) {
                return false;
            }
        }

        // Check excluded middleware
        foreach ($config['exclude_middleware'] ?? [] as $middleware) {
            if (in_array($middleware, $route->middleware())) {
                return false;
            }
        }

        // Check include patterns
        $includes = $config['include'] ?? [];

        if (empty($includes)) {
            return true;
        }

        foreach ($includes as $pattern) {
            if (Str::is($pattern, $uri)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Get allowed HTTP methods from route methods.
     */
    public function getAllowedMethods(Route $route, array $allowedMethods = ['get', 'post', 'put', 'patch', 'delete']): array
    {
        return array_values(
            array_intersect(
                array_map('strtolower', $route->methods()),
                $allowedMethods
            )
        );
    }

    /**
     * Normalize route path for OpenAPI.
     */
    public function normalizePath(Route $route): string
    {
        // Convert optional parameters {param?} to {param}
        $path = preg_replace('/\{([^}]+)\?}/', '{$1}', $route->uri());

        return '/'.ltrim($path, '/');
    }
}
