<?php

use Illuminate\Validation\Rule;
use Jurager\Documentator\OpenApi\SchemaBuilder;

beforeEach(function () {
    $this->builder = app(SchemaBuilder::class);
});

it('maps validation rules to OpenAPI types and formats', function (string $rules, array $expected) {
    $schema = $this->builder->build(['field' => $rules]);
    expect($schema['properties']['field'])->toMatchArray($expected);
})->with([
    ['required|integer', ['type' => 'integer']],
    ['numeric|min:0.25|max:99.95', ['type' => 'number', 'minimum' => 0.25, 'maximum' => 99.95]],
    ['string|min:2|max:10', ['type' => 'string', 'minLength' => 2, 'maxLength' => 10]],
    ['array|min:1|max:3', ['type' => 'array', 'minItems' => 1, 'maxItems' => 3]],
    ['nullable|boolean', ['type' => 'boolean', 'nullable' => true]],
    ['email', ['type' => 'string', 'format' => 'email']],
    ['url', ['type' => 'string', 'format' => 'uri']],
    ['uuid', ['type' => 'string', 'format' => 'uuid']],
    ['date', ['type' => 'string', 'format' => 'date']],
    ['json', ['type' => 'string']],
    ['file|max:2048', ['type' => 'string', 'format' => 'binary']],
    ['image', ['type' => 'string', 'format' => 'binary']],
    ['integer|in:1,2', ['type' => 'integer', 'enum' => [1, 2]]],
]);

it('uses standard type mappings without configuration', function () {
    expect((app(SchemaBuilder::class))->build(['id' => 'integer'])['properties']['id']['type'])->toBe('integer');
});

it('preserves nested metadata and required fields independently of rule order', function (bool $reverse) {
    $rules = [
        'rows' => 'required|array|min:1',
        'rows.*.profile.name' => 'required|string',
        'rows.*.cells.*.value' => 'required|integer',
        'settings.enabled' => 'required|boolean',
    ];
    $schema = $this->builder->build($reverse ? array_reverse($rules, true) : $rules);
    expect($schema['required'])->toBe(['rows'])
        ->and($schema['properties']['rows']['minItems'])->toBe(1)
        ->and($schema['properties']['rows']['items']['properties']['profile']['required'])->toBe(['name'])
        ->and($schema['properties']['rows']['items']['properties']['cells']['items']['required'])->toBe(['value'])
        ->and($schema['properties']['settings']['required'])->toBe(['enabled']);
})->with([false, true]);

it('merges nested annotations without dropping validation constraints', function () {
    $schema = $this->builder->build(['rows.*.name' => 'required|string|max:10'], [
        ['name' => 'rows[].name', 'type' => 'string', 'description' => 'Display name', 'required' => true, 'example' => 'desk'],
    ]);
    expect($schema['properties']['rows']['items']['properties']['name'])->toMatchArray([
        'maxLength' => 10, 'description' => 'Display name', 'example' => 'desk',
    ]);
});

it('supports scalar object file and multidimensional annotation arrays', function (string $type, array $expected) {
    $schema = $this->builder->build([], [['name' => 'values', 'type' => $type, 'description' => '', 'required' => false]]);
    expect($schema['properties']['values'])->toMatchArray($expected);
})->with([
    ['integer[]', ['type' => 'array', 'items' => ['type' => 'integer']]],
    ['object[]', ['type' => 'array', 'items' => ['type' => 'object']]],
    ['file[]', ['type' => 'array', 'items' => ['type' => 'string', 'format' => 'binary']]],
    ['integer[][]', ['type' => 'array', 'items' => ['type' => 'array', 'items' => ['type' => 'integer']]]],
]);

it('handles object rules and ignores callbacks', function () {
    $schema = $this->builder->build([
        'email' => [Rule::unique('users'), Rule::exists('users')],
        'color' => [Rule::in(['red', 'blue']), fn () => null],
    ]);
    expect($schema['properties']['email']['description'])->toContain('unique', 'must exist')
        ->and($schema['properties']['color']['enum'])->toBe(['red', 'blue']);
});

it('does not unconditionally require sometimes fields', function () {
    expect($this->builder->build(['name' => 'sometimes|required|string']))->not->toHaveKey('required');
});

it('builds valid schemas for empty arrays objects and optional nested fields', function () {
    $schema = $this->builder->build(['tags' => 'array', 'settings.name' => 'string']);
    assertValidOpenApi(array_replace_recursive($this->specification(), [
        'components' => ['schemas' => ['Request' => $schema]],
    ]));
});

it('normalizes rule strings and arrays without executing opaque callbacks', function () {
    $callback = fn () => throw new RuntimeException('Must not execute');
    $schema = app(SchemaBuilder::class)->build([
        'id' => 'required|integer', 'name' => ['string', $callback], 'opaque' => $callback,
    ]);
    expect($schema['properties']['id']['type'])->toBe('integer')
        ->and($schema['properties']['name']['type'])->toBe('string')
        ->and($schema['properties']['opaque']['type'])->toBe('string');
});
