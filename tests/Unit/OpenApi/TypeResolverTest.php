<?php

use Jurager\Documentator\OpenApi\TypeResolver;

it('infers types from field names', function (string $field, string $type) {
    expect(app(TypeResolver::class)->fromFieldName($field))->toBe($type);
})->with([
    ['id', 'integer'], ['owner_id', 'integer'], ['created_at', 'string'], ['items_count', 'integer'],
    ['price', 'number'], ['balance', 'number'], ['latitude', 'number'], ['is_active', 'boolean'],
    ['has_owner', 'boolean'], ['can_edit', 'boolean'], ['page', 'integer'], ['visible', 'boolean'], ['name', 'string'],
]);

it('maps validation types consistently', function (string $rule, string $type) {
    expect(app(TypeResolver::class)->fromValidationRule($rule))->toBe($type);
})->with([['integer', 'integer'], ['numeric', 'number'], ['boolean', 'boolean'], ['array', 'array'], ['date', 'string'], ['string', 'string']]);

it('prioritizes boolean prefixes and avoids treating account as a count', function () {
    $resolver = app(TypeResolver::class);
    expect($resolver->fromFieldName('has_discount'))->toBe('boolean')
        ->and($resolver->fromFieldName('isCountable'))->toBe('boolean')
        ->and($resolver->fromFieldName('account'))->toBe('string');
});

it('maps built-in Eloquent casts to schemas', function (string $cast, array $schema) {
    expect(app(TypeResolver::class)->fromCast($cast))->toEqual($schema);
})->with([
    ['integer', ['type' => 'integer']],
    ['decimal:2', ['type' => 'number']],
    ['boolean', ['type' => 'boolean']],
    ['array', ['type' => 'array', 'items' => (object) []]],
    ['object', ['type' => 'object']],
    ['date:Y-m-d', ['type' => 'string', 'format' => 'date']],
    ['immutable_datetime', ['type' => 'string', 'format' => 'date-time']],
    ['encrypted:array', ['type' => 'array', 'items' => (object) []]],
]);

it('rejects invalid OpenAPI type mappings', function () {
    config(['documentator.type_map' => ['money' => 'invalid']]);

    app(TypeResolver::class);
})->throws(InvalidArgumentException::class, 'type_map');

enum DocumentatorIntegerStatus: int
{
    case Pending = 1;
    case Complete = 2;
}

it('infers enum-backed model cast values', function () {
    expect(app(TypeResolver::class)->fromCast(DocumentatorIntegerStatus::class))->toBe([
        'type' => 'integer', 'enum' => [1, 2],
    ]);
});

it('rejects non-array validation type configuration with a useful error', function () {
    config(['documentator.type_map' => 'invalid']);

    app(TypeResolver::class);
})->throws(InvalidArgumentException::class, 'type_map setting must be an array');
