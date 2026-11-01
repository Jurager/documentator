<?php

use Illuminate\Routing\Route as RoutingRoute;
use Illuminate\Routing\RouteCollection;
use Illuminate\Support\Facades\Route;
use Jurager\Documentator\Routing\RouteCollector;

it('filters route patterns and excluded middleware with exclusion taking priority', function () {
    Route::get('api/widgets', fn () => []);
    Route::get('api/internal', fn () => []);
    Route::get('api/private', fn () => [])->middleware('internal');
    Route::get('web/home', fn () => []);
    $collector = app(RouteCollector::class);
    expect($collector->collect(['include' => ['api/*'], 'exclude' => ['api/internal'], 'exclude_middleware' => ['internal']])->map->uri()->all())
        ->toBe(['api/widgets'])
        ->and($collector->collect(['include' => ['missing/*']]))->toBeEmpty();
});

it('collects all routes when no include patterns are configured', function () {
    Route::setRoutes(new RouteCollection);
    Route::get('/', fn () => []);
    expect(app(RouteCollector::class)->collect(['include' => []]))->toHaveCount(1);
});

it('normalizes optional path placeholders and the root path', function (string $uri, string $path) {
    expect(app(RouteCollector::class)->normalizePath(new RoutingRoute('GET', $uri, fn () => [])))->toBe($path);
})->with([['api/widgets/{widget?}', '/api/widgets/{widget}'], ['/', '/']]);

it('limits HTTP methods and omits HEAD by default', function () {
    $route = new RoutingRoute(['GET', 'HEAD', 'OPTIONS'], '/', fn () => []);
    expect(app(RouteCollector::class)->getAllowedMethods($route))->toBe(['get'])
        ->and(app(RouteCollector::class)->getAllowedMethods($route, ['options']))->toBe(['options']);
});

it('uses configured methods consistently and excludes methods outside OpenAPI', function () {
    Route::match(['GET', 'TRACE', 'PROPFIND'], 'api/widgets', fn () => []);
    Route::post('api/widgets', fn () => []);
    config(['documentator.routes.methods' => ['GET', 'TRACE', 'PROPFIND']]);
    $collector = app(RouteCollector::class);
    $routes = $collector->collect();

    expect($routes)->toHaveCount(1)
        ->and($collector->getAllowedMethods($routes->first()))->toBe(['get', 'trace']);
});
