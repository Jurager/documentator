<?php

use Illuminate\Support\Facades\Route;
use Jurager\Documentator\Formats\JsonApiFormat;
use Jurager\Documentator\Formats\SimpleFormat;
use Jurager\Documentator\OpenApi\ExampleGenerator;
use Jurager\Documentator\Parsers\ResourceParser;
use Jurager\Documentator\Tests\Fixtures\WidgetResource;

it('uses operation status codes independently of the response format', function (string $format, string $method, int $status) {
    Route::match([strtoupper($method)], 'api/example', fn () => []);
    $specification = $this->specification(['format' => $format]);
    $response = $specification['paths']['/api/example'][$method]['responses'];
    expect($response)->toHaveKey($status);
    if ($status === 204) {
        expect($response[$status])->not->toHaveKey('content');
    } else {
        expect($response[$status]['content'])->toHaveKey($format === SimpleFormat::class ? 'application/json' : 'application/vnd.api+json');
    }
    assertValidOpenApi($specification);
})->with([SimpleFormat::class, JsonApiFormat::class])->with([['get', 200], ['post', 201], ['put', 200], ['patch', 200], ['delete', 204]]);

it('generates valid reusable schemas and response references', function (string $format) {
    $instance = app($format);
    assertValidOpenApi($this->specification(['format' => $format]));
    expect($instance->responses()['Unauthorized']['description'])->toBe('Unauthorized')
        ->and($instance->responses()['Unauthorized']['content'])->toHaveKey($format === SimpleFormat::class ? 'application/json' : 'application/vnd.api+json')
        ->and($instance->responses()['NoContent'])->not->toHaveKey('content');
})->with([SimpleFormat::class, JsonApiFormat::class]);

it('includes detected attribute types in response schemas', function (string $format, bool $collection) {
    $data = ['attributes' => ['name' => ['type' => 'string'], 'price' => ['type' => 'number'], 'labels' => ['type' => 'array']]];
    $response = app($format)->response('widgets', $data, $collection);
    $content = array_values($response['content'])[0]['schema'];
    $resource = $collection ? $content['properties']['data']['items'] : $content['properties']['data'];
    $properties = $format === SimpleFormat::class ? $resource['properties'] : $resource['properties']['attributes']['properties'];
    expect($properties['price']['type'])->toBe('number')
        ->and($properties['labels'])->toHaveKey('items');
})->with([SimpleFormat::class, JsonApiFormat::class])->with([false, true]);

it('represents both to-one and to-many JSON API relationships', function () {
    $data = app(ResourceParser::class)->parse(WidgetResource::class);
    $schema = app(JsonApiFormat::class)->response('widgets', $data)['content']['application/vnd.api+json']['schema'];
    $relationships = $schema['properties']['data']['properties']['relationships']['properties'];
    $example = json_decode(json_encode(app(ExampleGenerator::class)->generate($schema, items: 2)), true);
    expect($relationships['owner']['properties']['data']['type'])->toBe('object')
        ->and($relationships['children']['properties']['data']['type'])->toBe('array')
        ->and($example['data']['relationships']['children']['data'])->toHaveCount(2);
});

it('handles resources containing only an identifier', function () {
    $schema = app(JsonApiFormat::class)->response('widgets', ['attributes' => ['id' => ['type' => 'integer']]])['content']['application/vnd.api+json']['schema'];
    $example = app(ExampleGenerator::class)->generate($schema);
    expect(json_encode($schema['properties']['data']['properties']['attributes']['properties']))->toBe('{}')
        ->and(json_encode($example->data->attributes))->toBe('{}');
});
