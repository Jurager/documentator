<?php

use Faker\Factory;
use Faker\Generator;
use JsonSchema\Validator;
use Jurager\Documentator\OpenApi\ExampleGenerator;
use Jurager\Documentator\OpenApi\SchemaBuilder;

it('preserves explicit false zero null and empty array examples', function () {
    $example = app(ExampleGenerator::class)->generate(['type' => 'object', 'properties' => [
        'enabled' => ['example' => false], 'count' => ['example' => 0], 'name' => ['example' => null], 'tags' => ['example' => []],
    ]]);
    expect((array) $example)->toBe(['enabled' => false, 'count' => 0, 'name' => null, 'tags' => []]);
});

it('generates examples satisfying enum bounds and nested schemas', function () {
    $schema = app(SchemaBuilder::class)->build([
        'color' => 'string|in:red,blue', 'name' => 'string|min:8|max:10', 'price' => 'numeric|min:0.25|max:0.5',
        'tags' => 'array|min:3|max:4', 'tags.*' => 'integer|in:1,2', 'settings.enabled' => 'required|boolean',
        'matrix.*.*' => 'integer', 'empty' => 'array|max:0', 'contact' => 'email',
    ]);
    $example = app(ExampleGenerator::class)->generate($schema);
    $jsonSchema = json_decode(json_encode($schema));
    $validator = new Validator;
    $validator->validate($example, $jsonSchema);
    expect($validator->isValid())->toBeTrue(json_encode($validator->getErrors()))
        ->and($example->color)->toBe('red')
        ->and($example->tags)->toHaveCount(3)
        ->and($example->empty)->toBe([]);
});

it('supports injected seeded Faker generators', function () {
    $faker = Factory::create();
    $faker->seed(42);
    $generator = new ExampleGenerator($faker);
    $value = $generator->generate(['type' => 'object', 'properties' => ['email' => ['type' => 'string']]])->email;
    $faker->seed(42);
    expect($generator->generate(['type' => 'object', 'properties' => ['email' => ['type' => 'string']]])->email)->toBe($value);
});

it('generates typed primitive examples', function (string $type, string $expectedType) {
    expect(get_debug_type(app(ExampleGenerator::class)->generate(['type' => $type])))->toBe($expectedType);
})->with([['integer', 'int'], ['number', 'float'], ['boolean', 'bool'], ['string', 'string'], ['array', 'array'], ['object', 'stdClass']]);

it('keeps inactive boolean examples consistent', function () {
    $generator = app(ExampleGenerator::class);
    expect($generator->generate(['type' => 'object', 'properties' => ['is_disabled' => ['type' => 'boolean']]])->is_disabled)->toBeFalse()
        ->and($generator->generate(['type' => 'object', 'properties' => ['is_active' => ['type' => 'boolean']]])->is_active)->toBeTrue()
        ->and($generator->generate(['type' => 'integer', 'minimum' => 10, 'maximum' => 10]))->toBe(10);
});

it('generates object examples as JSON objects even when empty', function () {
    $result = app(ExampleGenerator::class)->generate(['type' => 'object', 'properties' => ['settings' => ['type' => 'object']]]);
    expect(json_encode($result))->toBe('{"settings":{}}');
});

it('resolves reusable schemas and stops recursive references', function () {
    $generator = app(ExampleGenerator::class);
    expect($generator->generate(['$ref' => '#/components/schemas/Id'], ['Id' => ['type' => 'integer']]))->toBeInt()
        ->and($generator->generate(['$ref' => '#/components/schemas/Loop'], ['Loop' => ['$ref' => '#/components/schemas/Loop']]))->toBeNull();
});

it('generates distinct collection identifiers and RFC3339 date-time values', function () {
    $generator = app(ExampleGenerator::class);
    $values = $generator->generate(['type' => 'array', 'items' => [
        'type' => 'object', 'properties' => ['id' => ['type' => 'string'], 'created_at' => ['type' => 'string', 'format' => 'date-time']],
    ]], items: 2);
    expect($values[0]->id)->toBe('1')->and($values[1]->id)->toBe('2')
        ->and($values[0]->created_at)->toMatch('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}[+-]\d{2}:\d{2}$/');
});

it('measures string bounds in characters when Faker returns Unicode', function () {
    $faker = Mockery::mock(Generator::class);
    $faker->shouldReceive('word')->once()->andReturn('Ж');
    $schema = ['type' => 'string', 'minLength' => 3, 'maxLength' => 3];
    $example = (new ExampleGenerator($faker))->generate($schema);

    expect($example)->toBe('Жxx');
    assertOpenApiExample($schema, $example);
});

it('preserves named response examples without adding a conflicting example', function () {
    $document = ['components' => ['schemas' => []], 'paths' => ['/api/widgets' => ['get' => ['responses' => [200 => [
        'description' => 'Success', 'content' => ['application/json' => [
            'schema' => ['type' => 'string'], 'examples' => ['first' => ['value' => 'explicit']],
        ]],
    ]]]]]];

    $result = app(ExampleGenerator::class)->populate($document, [], 0);

    expect($result['paths']['/api/widgets']['get']['responses'][200]['content']['application/json'])
        ->toHaveKey('examples')->not->toHaveKey('example');
});
