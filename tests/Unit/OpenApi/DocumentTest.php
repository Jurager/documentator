<?php

use Jurager\Documentator\OpenApi\Document;

it('keeps operation IDs unique when generated suffixes collide with explicit names', function () {
    $document = new Document($this->specification());
    $suffix = substr(sha1('get /api/third'), 0, 10);
    $ids = ['same', 'same_'.$suffix, 'same'];

    foreach (['/api/first', '/api/second', '/api/third'] as $index => $path) {
        $document->addOperation($path, 'get', ['operationId' => $ids[$index], 'responses' => [204 => ['description' => 'No Content']]]);
    }

    $specification = $document->toArray();
    expect($specification['paths']['/api/third']['get']['operationId'])->toBe('same_'.$suffix.'_1');
    assertValidOpenApi($specification);
});

it('registers distinct request components for each media type', function () {
    $document = new Document($this->specification());
    $document->addOperation('/api/widgets', 'post', [
        'operationId' => 'widgets:store',
        'requestBody' => ['content' => [
            'application/json' => ['schema' => ['type' => 'object', 'properties' => ['name' => ['type' => 'string']]]],
            'multipart/form-data' => ['schema' => ['type' => 'object', 'properties' => ['file' => ['type' => 'string', 'format' => 'binary']]]],
        ]],
        'responses' => [201 => ['description' => 'Created']],
    ]);

    $specification = $document->toArray();
    $schemas = $document->requestSchemas();
    expect($schemas)->toHaveCount(2)
        ->and($schemas[0])->not->toBe($schemas[1])
        ->and($specification['components']['schemas'][$schemas[0]]['properties'])->toHaveKey('name')
        ->and($specification['components']['schemas'][$schemas[1]]['properties'])->toHaveKey('file');
    foreach ($schemas as $name) {
        expect($name)->toMatch('/^[a-zA-Z0-9._-]+$/');
    }
    assertValidOpenApi($specification);
});

it('preserves component names already owned by a custom format', function () {
    $operation = [
        'operationId' => 'widgets_store',
        'requestBody' => ['content' => ['application/json' => ['schema' => ['type' => 'object']]]],
        'responses' => [201 => ['description' => 'Created']],
    ];
    $first = new Document($this->specification());
    $first->addOperation('/api/widgets', 'post', $operation);
    $reserved = $first->requestSchemas()[0];

    $attributes = $this->specification();
    $attributes['components']['schemas'][$reserved] = ['type' => 'string'];
    $document = new Document($attributes);
    $document->addOperation('/api/widgets', 'post', $operation);
    $specification = $document->toArray();

    expect($document->requestSchemas()[0])->not->toBe($reserved)
        ->and($specification['components']['schemas'][$reserved])->toBe(['type' => 'string']);
    assertValidOpenApi($specification);
});

it('serializes an empty document as OpenAPI objects without mutating its state', function () {
    $document = new Document(['openapi' => '3.0.3', 'info' => ['title' => 'API', 'version' => '1.0.0'], 'components' => ['schemas' => []]]);

    expect(json_encode($document))->toContain('"paths":{}', '"schemas":{}');
    $document->addOperation('/api/widgets', 'get', ['operationId' => 'widgets', 'responses' => [204 => ['description' => 'No Content']]]);
    assertValidOpenApi($document);
});
