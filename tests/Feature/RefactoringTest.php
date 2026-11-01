<?php

use Faker\Generator;
use Illuminate\Routing\RouteCollection;
use Illuminate\Support\Facades\Route;
use Jurager\Documentator\Contracts\ResponseFormat;
use Jurager\Documentator\Documentator;
use Jurager\Documentator\Linting\ParameterLinter;
use Jurager\Documentator\OpenApi\ExampleGenerator;
use Jurager\Documentator\OpenApi\SchemaBuilder;
use Jurager\Documentator\OpenApi\TypeResolver;
use Jurager\Documentator\Parsers\RouteParser;
use Jurager\Documentator\Parsers\ValidationParser;
use Jurager\Documentator\Tests\Fixtures\DependencyFormat;
use Jurager\Documentator\Tests\Fixtures\WidgetsController;

it('resolves the configured generation entry point through Laravel', function () {
    Route::get('api/widgets', [WidgetsController::class, 'index']);
    assertValidOpenApi(app(Documentator::class)->generate());
});

it('uses the application Faker binding for schema examples', function () {
    $faker = Mockery::mock(Generator::class);
    $faker->shouldReceive('safeEmail')->once()->andReturn('example@laravel.test');
    app()->instance(Generator::class, $faker);

    expect(app(ExampleGenerator::class)->generate(['type' => 'string', 'format' => 'email']))
        ->toBe('example@laravel.test');
});

it('applies configured validation type mappings to generated schemas', function () {
    config(['documentator.type_map' => ['money' => 'number']]);

    $schema = app(SchemaBuilder::class)->build(['price' => 'required|money|min:0.01']);

    expect($schema['properties']['price'])->toMatchArray(['type' => 'number', 'minimum' => 0.01])
        ->and($schema['required'])->toContain('price');
});

it('generates the exact same document on repeated builds with a seed', function (string $format) {
    Route::apiResource('api/widgets', WidgetsController::class);
    Route::get('api/casted-widgets', [WidgetsController::class, 'nested']);
    config(['documentator.format' => $format, 'documentator.examples.seed' => 1234]);
    $documentator = app(Documentator::class);
    $first = json_encode($documentator->generate(), JSON_THROW_ON_ERROR);
    $second = json_encode($documentator->generate(), JSON_THROW_ON_ERROR);
    expect(hash('sha256', $second))->toBe(hash('sha256', $first));
})->with(['simple', 'json-api']);

it('changes automatic examples when the configured seed changes', function () {
    Route::get('api/widgets', [WidgetsController::class, 'index']);
    $first = $this->specification(['examples' => ['seed' => 1]]);
    $second = $this->specification(['examples' => ['seed' => 2]]);
    expect(json_encode($first))->not->toBe(json_encode($second));
});

it('disables automatic examples and preserves explicit annotations', function (string $format) {
    Route::apiResource('api/widgets', WidgetsController::class);
    Route::post('api/examples', [WidgetsController::class, 'examples']);
    $specification = $this->specification(['format' => $format, 'examples' => ['enabled' => false]]);
    $request = collect($specification['components']['schemas'])->first(fn ($schema) => isset($schema['properties']['name']));
    expect($request)->not->toHaveKey('example');
    $response = array_values($specification['paths']['/api/widgets']['get']['responses'][200]['content'])[0];
    expect($response)->not->toHaveKey('example')
        ->and(json_encode($specification['paths']['/api/examples']['post']['responses'][200]['content']['application/json']['example']))->toBe('{}');
    assertValidOpenApi($specification);
})->with(['simple', 'json-api']);

it('resolves custom format constructor dependencies without Faker', function () {
    config(['documentator.format' => DependencyFormat::class]);
    app()->bind(ExampleGenerator::class, fn () => throw new RuntimeException('Examples must not be constructed.'));
    $format = app(ResponseFormat::class);
    expect($format)->toBeInstanceOf(DependencyFormat::class)
        ->and($format->types)->toBeInstanceOf(TypeResolver::class);
});

it('lints without constructing responses examples or invoking request rules', function () {
    Route::get('api/widgets', [WidgetsController::class, 'index']);
    Route::post('api/failure', [WidgetsController::class, 'failing']);
    app()->bind(ResponseFormat::class, fn () => throw new RuntimeException('Formats must not be constructed.'));
    app()->bind(ExampleGenerator::class, fn () => throw new RuntimeException('Examples must not be constructed.'));
    $validation = Mockery::mock(ValidationParser::class);
    $validation->shouldNotReceive('parse');
    app()->instance(ValidationParser::class, $validation);
    expect(app(ParameterLinter::class)->lint())->toBe([]);
});

it('inspects a multi-method route once per build', function () {
    Route::match(['PUT', 'PATCH'], 'api/widgets/{widget}', [WidgetsController::class, 'update']);
    $real = app(RouteParser::class);
    $parser = Mockery::mock(RouteParser::class);
    $parser->shouldReceive('reset')->andReturnUsing(fn () => $real->reset());
    $parser->shouldReceive('warnings')->andReturnUsing(fn () => $real->warnings());
    $parser->shouldReceive('parse')->once()->andReturnUsing(fn ($route) => $real->parse($route));
    app()->instance(RouteParser::class, $parser);
    $specification = app(Documentator::class)->generate();
    expect($specification['paths']['/api/widgets/{widget}'])->toHaveKeys(['put', 'patch']);
});

it('preserves nested resource arrays and honors model casts and explicit PHP casts', function () {
    Route::get('api/casted-widgets', [WidgetsController::class, 'nested']);
    $specification = $this->specification();
    $properties = $specification['paths']['/api/casted-widgets']['get']['responses'][200]['content']['application/json']['schema']['properties']['data']['items']['properties'];
    expect($properties)->not->toHaveKey('ignored')->not->toHaveKey('value')
        ->and($properties['id']['type'])->toBe('string')
        ->and($properties['rating']['type'])->toBe('number')
        ->and($properties['flag']['type'])->toBe('boolean')
        ->and($properties['options']['type'])->toBe('object')
        ->and($properties['created_at']['format'])->toBe('date-time')
        ->and($properties['external_id']['type'])->toBe('string')
        ->and($properties['profile']['properties']['active']['type'])->toBe('boolean')
        ->and($properties['entries']['items']['properties']['value']['type'])->toBe('integer');
    assertValidOpenApi($specification);
});

it('supports dependency injection in FormRequest rules', function () {
    Route::post('api/dependency', [WidgetsController::class, 'dependency']);
    $specification = $this->specification();
    $request = collect($specification['components']['schemas'])->first(fn ($schema) => isset($schema['properties']['enabled']));
    expect($request['properties']['enabled']['type'])->toBe('boolean');
    assertValidOpenApi($specification);
});

it('keeps explicit normalized parameters ahead of presets and inferred values', function () {
    Route::get('api/widgets', [WidgetsController::class, 'overrides']);
    $specification = $this->specification();
    $parameters = collect($specification['paths']['/api/widgets']['get']['parameters']);
    expect($parameters->where('name', 'filter[parent_id]'))->toHaveCount(1)
        ->and($parameters->firstWhere('name', 'filter[parent_id]')['description'])->toBe('Explicit parent filter.')
        ->and($parameters->where('name', 'page[size]'))->toHaveCount(1)
        ->and($parameters->firstWhere('name', 'page[size]')['description'])->toBe('Explicit page size.');
    assertValidOpenApi($specification);
});

it('exposes extraction failures and resets warnings between builds', function () {
    Route::post('api/failure', [WidgetsController::class, 'failing']);
    $documentator = app(Documentator::class);
    $documentator->generate();
    expect(implode("\n", $documentator->warnings()))->toContain('FailingRequest', 'unavailable request data');
    Route::setRoutes(new RouteCollection);
    $documentator->generate();
    expect($documentator->warnings())->toBe([]);
});

it('detects differences in array item types while linting', function () {
    Route::get('api/one', [WidgetsController::class, 'integerIds']);
    Route::get('api/two', [WidgetsController::class, 'stringIds']);
    $issues = app(ParameterLinter::class)->lint();
    expect($issues)->toHaveCount(1)->and($issues[0]['name'])->toBe('ids')
        ->and($issues[0]['variants'])->toHaveCount(2);
});

it('ignores header casing when comparing shared parameter names', function () {
    Route::get('api/one', [WidgetsController::class, 'headerUpper']);
    Route::get('api/two', [WidgetsController::class, 'headerLower']);
    $issues = app(ParameterLinter::class)->lint();
    expect($issues)->toHaveCount(1);
});

it('uses fixed date bounds for repeatable schema examples', function () {
    $examples = app(ExampleGenerator::class);
    $examples->reset(42);
    $schema = ['type' => 'object', 'properties' => ['created_at' => ['type' => 'string', 'format' => 'date-time']]];
    $first = $examples->generate($schema);
    $examples->reset(42);
    $second = $examples->generate($schema);
    expect($first)->toEqual($second)->and(strtotime($first->created_at))->toBeLessThanOrEqual(strtotime('2020-01-01'));
});

it('generates request and response examples conforming to their OpenAPI schemas', function (string $format) {
    Route::apiResource('api/widgets', WidgetsController::class);
    Route::get('api/casted-widgets', [WidgetsController::class, 'nested']);
    $specification = $this->specification(['format' => $format]);
    $components = $specification['components']['schemas'];
    foreach ($components as $schema) {
        if (array_key_exists('example', $schema)) {
            assertOpenApiExample($schema, $schema['example'], $components);
        }
    }
    foreach ($specification['paths'] as $operations) {
        foreach ($operations as $operation) {
            foreach ($operation['responses'] as $response) {
                foreach ($response['content'] ?? [] as $content) {
                    if (isset($content['schema']) && array_key_exists('example', $content)) {
                        assertOpenApiExample($content['schema'], $content['example'], $components);
                    }
                }
            }
        }
    }
})->with(['simple', 'json-api']);

it('rejects invalid example configuration', function (array $settings) {
    $this->specification(['examples' => $settings]);
})->with([[['enabled' => 'false']], [['seed' => 'invalid']]])->throws(InvalidArgumentException::class);

it('keeps operation IDs and request schemas distinct when route names collide', function () {
    Route::post('api/first', [WidgetsController::class, 'store'])->name('same');
    Route::post('api/second', [WidgetsController::class, 'store'])->name('same');
    $specification = $this->specification();
    assertValidOpenApi($specification);
    $first = $specification['paths']['/api/first']['post'];
    $second = $specification['paths']['/api/second']['post'];
    expect($first['operationId'])->not->toBe($second['operationId'])
        ->and($first['requestBody']['content']['application/json']['schema']['$ref'])->not->toBe($second['requestBody']['content']['application/json']['schema']['$ref']);
});

it('uses the explicitly bound model before guessing from the route name', function () {
    Route::get('api/widgets/{widget}', [WidgetsController::class, 'boundCasted']);
    $specification = $this->specification();
    $operation = $specification['paths']['/api/widgets/{widget}']['get'];

    expect($operation['parameters'][0]['schema']['type'])->toBe('string')
        ->and($operation['responses'][200]['content']['application/json']['schema']['properties']['data']['properties']['id']['type'])->toBe('string');
    assertValidOpenApi($specification);
});

it('rejects routes that would overwrite the same OpenAPI operation', function () {
    Route::domain('one.test')->post('api/widgets', [WidgetsController::class, 'store']);
    Route::domain('two.test')->post('api/widgets', [WidgetsController::class, 'store']);

    $this->specification();
})->throws(InvalidArgumentException::class, 'Multiple routes describe post /api/widgets');

it('clears generation state before rejecting invalid configuration', function () {
    Route::post('api/failure', [WidgetsController::class, 'failing']);
    $documentator = app(Documentator::class);
    $documentator->generate();
    expect($documentator->getProcessedCount())->toBe(1)->and($documentator->warnings())->not->toBeEmpty();

    config(['documentator.openapi_version' => '2.0']);
    expect(fn () => $documentator->generate())->toThrow(InvalidArgumentException::class);
    expect($documentator->getProcessedCount())->toBe(0)->and($documentator->warnings())->toBe([]);
});
