<?php

use Illuminate\Support\Facades\Route;
use Jurager\Documentator\Tests\Fixtures\WidgetsController;

it('generates required path parameters even if PHPDoc marks them optional', function () {
    Route::get('api/widgets/{widget?}', [WidgetsController::class, 'show']);
    $spec = $this->specification();
    $parameter = $spec['paths']['/api/widgets/{widget}']['get']['parameters'][0];
    expect($parameter)->toMatchArray(['name' => 'widget', 'in' => 'path', 'required' => true, 'example' => 42, 'schema' => ['type' => 'integer']]);
    assertValidOpenApi($spec);
});

it('normalizes filter dot notation and preserves explicit query examples', function () {
    Route::get('api/widgets', [WidgetsController::class, 'index']);
    $parameters = collect($this->specification()['paths']['/api/widgets']['get']['parameters']);
    expect($parameters->firstWhere('name', 'filter[name]'))->toMatchArray(['example' => 'desk', 'schema' => ['type' => 'string']])
        ->and($parameters->firstWhere('name', 'X-Tenant'))->toMatchArray(['in' => 'header', 'required' => true]);
});

it('adds configured default responses without replacing documented ones', function () {
    Route::post('api/queue', [WidgetsController::class, 'queue']);
    $spec = $this->specification(['responses' => ['default' => [
        202 => ['description' => 'Ignored'], 401 => ['$ref' => '#/components/responses/Unauthorized'],
    ]]]);
    expect($spec['paths']['/api/queue']['post']['responses'][202]['description'])->not->toBe('Ignored')
        ->and($spec['paths']['/api/queue']['post']['responses'][401])->toBe(['$ref' => '#/components/responses/Unauthorized']);
    assertValidOpenApi($spec);
});

it('derives include parameters for a single resource and sort parameters for a collection', function () {
    Route::apiResource('api/widgets', WidgetsController::class);
    $spec = $this->specification();
    $index = array_column($spec['paths']['/api/widgets']['get']['parameters'], 'name');
    $show = array_column($spec['paths']['/api/widgets/{widget}']['get']['parameters'], 'name');
    expect($index)->toContain('sort', 'include', 'filter[parent_id]', 'filter[owner][name]')
        ->and($show)->toContain('include')->not->toContain('sort', 'filter[parent_id]');
});

it('preserves array query schemas and scalar header examples', function () {
    Route::get('api/parameters', [WidgetsController::class, 'parameters']);
    $spec = $this->specification();
    $operation = $spec['paths']['/api/parameters']['get'];
    expect($operation['parameters'][0])->toMatchArray(['schema' => ['type' => 'array', 'items' => ['type' => 'integer']], 'example' => [1, 2]])
        ->and($operation['parameters'][2]['example'])->toBeFalse()
        ->and($operation['responses'][204])->not->toHaveKey('content');
    assertValidOpenApi($spec);
});

it('infers integer model keys and string custom binding keys', function () {
    Route::get('api/widgets/{widget}', [WidgetsController::class, 'bound']);
    Route::get('api/slugs/{widget:slug}', [WidgetsController::class, 'bound']);
    $spec = $this->specification();
    expect($spec['paths']['/api/widgets/{widget}']['get']['parameters'][0]['schema']['type'])->toBe('integer')
        ->and($spec['paths']['/api/slugs/{widget}']['get']['parameters'][0]['schema']['type'])->toBe('string');
    assertValidOpenApi($spec);
});

it('detects deeply nested file uploads and makes optional request bodies optional', function () {
    Route::post('api/nested-upload', [WidgetsController::class, 'nestedUpload']);
    $body = $this->specification()['paths']['/api/nested-upload']['post']['requestBody'];
    expect($body['required'])->toBeFalse()
        ->and($body['content'])->toHaveKey('multipart/form-data');
});

it('omits request bodies containing no fields', function () {
    Route::post('api/path-only/{widget}', [WidgetsController::class, 'pathOnly']);
    expect($this->specification()['paths']['/api/path-only/{widget}']['post'])->not->toHaveKey('requestBody');
});

it('preserves JSON object array and scalar examples without changing their types', function () {
    Route::post('api/examples', [WidgetsController::class, 'examples']);
    $spec = $this->specification();
    $responses = $spec['paths']['/api/examples']['post']['responses'];
    foreach ([200 => '{}', 201 => '[]', 202 => 'true', 400 => '0', 404 => 'null', 500 => '"failed"'] as $status => $json) {
        expect(json_encode($responses[$status]['content']['application/json']['example']))->toBe($json);
    }
    $request = collect($spec['components']['schemas'])->first(fn ($schema) => isset($schema['properties']['settings']));
    expect(json_encode($request['properties']['settings']['example']))->toBe('{}');
    assertValidOpenApi($spec);
});
