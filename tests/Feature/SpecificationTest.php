<?php

use Illuminate\Routing\RouteCollection;
use Illuminate\Support\Facades\Route;
use Jurager\Documentator\Documentator;
use Jurager\Documentator\Formats\SimpleFormat;
use Jurager\Documentator\Tests\Fixtures\WidgetsController;

it('generates a valid complete CRUD document for each response format', function (string $format) {
    Route::apiResource('api/widgets', WidgetsController::class);
    Route::post('api/queue', [WidgetsController::class, 'queue']);
    Route::post('api/upload', [WidgetsController::class, 'upload']);
    Route::post('api/inline', [WidgetsController::class, 'inline']);

    $spec = $this->specification(['format' => $format]);
    assertValidOpenApi($spec);
    expect($spec['security'])->toBe([['bearerAuth' => []]])
        ->and($spec['paths']['/api/queue']['post']['security'])->toBe([])
        ->and($spec['paths']['/api/queue']['post']['deprecated'])->toBeTrue()
        ->and((array) $spec['paths']['/api/queue']['post']['responses'][202]['content']['application/json']['example'])->toBe(['queued' => true])
        ->and($spec['paths']['/api/widgets/{widget}']['delete']['responses'][204])->toBe(['description' => 'No Content'])
        ->and($spec['paths']['/api/upload']['post']['requestBody']['content'])->toHaveKey('multipart/form-data');
})->with(['simple', 'json-api']);

it('generates a valid empty document and empty security maps', function () {
    config(['documentator.security.schemes' => [], 'documentator.security.default' => []]);
    $spec = $this->specification();
    assertValidOpenApi($spec);
    expect(json_encode($spec['paths']))->toBe('{}');
});

it('keeps operation IDs unique for unnamed paths and routes with multiple methods', function () {
    Route::get('api/widgets', fn () => []);
    Route::get('api/widgets/{widget}', fn () => []);
    Route::match(['PUT', 'PATCH'], 'api/widgets/{widget}', fn () => [])->name('widgets.update');
    assertValidOpenApi($this->specification());
});

it('keeps request components distinct for similar route segments', function () {
    Route::post('api/widgets', [WidgetsController::class, 'store']);
    Route::post('api/widgets/{widget}', [WidgetsController::class, 'update']);
    $spec = $this->specification();
    $first = $spec['paths']['/api/widgets']['post']['requestBody']['content']['application/json']['schema']['$ref'];
    $second = $spec['paths']['/api/widgets/{widget}']['post']['requestBody']['content']['application/json']['schema']['$ref'];
    expect($first)->not->toBe($second);
});

it('removes path fields from request properties required fields and examples', function () {
    Route::put('api/widgets/{widget}', [WidgetsController::class, 'update']);
    $spec = $this->specification();
    $request = collect($spec['components']['schemas'])->first(fn ($schema) => isset($schema['properties']['name']));
    expect($request['properties'])->not->toHaveKey('widget')
        ->and($request['required'])->not->toContain('widget')
        ->and($request['example'])->not->toHaveKey('widget');
});

it('does not retain components and tags from a previous build', function () {
    Route::post('api/widgets', [WidgetsController::class, 'store']);
    $documentator = app(Documentator::class);
    $documentator->generate();
    Route::setRoutes(new RouteCollection);
    $spec = $documentator->generate();
    expect($spec)->not->toHaveKey('tags')
        ->and($spec['components']['schemas'])->toHaveCount(2);
});

it('preserves server variables relative root URLs and metadata', function () {
    $spec = $this->specification(['servers' => [['url' => '/', 'variables' => ['host' => ['default' => '', 'enum' => ['']]]]]]);
    assertValidOpenApi($spec);
    expect($spec['servers'][0]['url'])->toBe('/')
        ->and($spec['servers'][0]['variables']['host']['default'])->toBe('');
});

it('supports associative and alternative security requirements', function (array $security, array $expected) {
    config(['documentator.security.default' => $security]);
    $spec = $this->specification();
    expect(json_encode($spec['security']))->toBe(json_encode($expected));
    assertValidOpenApi($spec);
})->with([
    'AND map' => [['bearerAuth' => []], [['bearerAuth' => []]]],
    'OR list' => [[['bearerAuth' => []], []], [['bearerAuth' => []], (object) []]],
]);

it('rejects unsupported OpenAPI versions', function (string $version) {
    $this->specification(['openapi_version' => $version]);
})->with(['2.0', '3.1.0', '3.2.0'])->throws(InvalidArgumentException::class, 'OpenAPI 3.0.x');

it('accepts built-in aliases and custom format classes', function (string $format) {
    $spec = $this->specification(['format' => $format]);
    assertValidOpenApi($spec);
})->with([SimpleFormat::class, 'simple']);

it('rejects custom formats that do not implement the format contract', function () {
    $this->specification(['format' => stdClass::class]);
})->throws(InvalidArgumentException::class, 'must implement');

it('honors configured tag definitions and disables auto generation', function () {
    Route::get('api/widgets', [WidgetsController::class, 'index']);
    $spec = $this->specification(['tags' => ['auto_generate' => false, 'definitions' => ['Z' => 'Last', 'A' => 'First']]]);
    expect($spec['tags'])->toBe([['name' => 'A', 'description' => 'First'], ['name' => 'Z', 'description' => 'Last']]);
});

it('reports progress once per route and counts collected routes', function () {
    Route::match(['PUT', 'PATCH'], 'api/widgets/{widget}', fn () => []);
    $documentator = app(Documentator::class);
    $progress = [];
    $callback = function ($route, $path, $methods) use (&$progress) {
        $progress[] = [$path, $methods];
    };
    $documentator->generate(progress: $callback);
    expect($progress)->toBe([['/api/widgets/{widget}', ['put', 'patch']]])
        ->and($documentator->getProcessedCount())->toBe(1);
});

it('ignores routes whose HTTP methods are not allowed', function () {
    Route::options('api/widgets', fn () => []);
    $spec = $this->specification();
    expect((array) $spec['paths'])->toBe([]);
});

it('keeps valid zero-valued metadata strings', function () {
    $spec = $this->specification(['info' => ['title' => '0', 'version' => '0', 'description' => '0']]);
    expect($spec['info'])->toMatchArray(['title' => '0', 'version' => '0', 'description' => '0']);
    assertValidOpenApi($spec);
});
