<?php

namespace Jurager\Documentator\Routing;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Routing\Route;
use Illuminate\Support\Str;

class RouteInformation
{
    public array $doc = [];

    public ?Model $model = null;

    public ?string $resourceClass = null;

    public array $data = [];

    public array $bindings = [];

    public string $resource;

    public function __construct(public Route $route)
    {
        $segments = $this->segments();
        $this->resource = Str::singular(Str::snake(end($segments) ?: 'resource'));
    }

    public function segments(): array
    {
        return array_values(array_filter(
            explode('/', $this->route->uri()),
            fn ($segment) => $segment !== '' && ! str_starts_with($segment, '{'),
        ));
    }

    public function isCollection(string $method): bool
    {
        $segments = explode('/', trim($this->route->uri(), '/'));

        return $method === 'get' && ! str_starts_with(end($segments), '{');
    }

    public function tags(): array
    {
        if (isset($this->doc['group'])) {
            return [$this->doc['group']];
        }

        $name = explode('.', $this->route->getName() ?? '');
        if (count($name) > 1) {
            return [Str::headline($name[0])];
        }

        $segments = $this->segments();

        return [Str::headline(end($segments) ?: 'General')];
    }
}
