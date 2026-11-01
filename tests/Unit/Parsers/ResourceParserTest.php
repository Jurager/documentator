<?php

use Jurager\Documentator\Parsers\ResourceParser;
use Jurager\Documentator\Tests\Fixtures\Widget;
use Jurager\Documentator\Tests\Fixtures\WidgetResource;
use Jurager\Documentator\Tests\Fixtures\WidgetsController;
use Jurager\Documentator\Tests\Fixtures\WidgetView;

it('discovers resources from return types constructors and static collection calls', function (string $method) {
    expect(app(ResourceParser::class)->find(new ReflectionMethod(WidgetsController::class, $method), 'widgets', []))->toBe(WidgetResource::class);
})->with(['index', 'show', 'store', 'aliased']);

it('parses resource attributes and relationships without executing them', function () {
    $data = app(ResourceParser::class)->parse(WidgetResource::class);
    expect($data['attributes'])->toBe([
        'id' => ['type' => 'integer'], 'name' => ['type' => 'string'], 'price' => ['type' => 'number'], 'is_active' => ['type' => 'boolean'],
    ])->and($data['relationships']['children'])->toMatchArray(['collection' => true, 'resource' => WidgetResource::class])
        ->and(app(ResourceParser::class)->parse(WidgetResource::class))->toBe($data);
});

it('guesses resources and models using configured namespaces', function () {
    $namespaces = ['Jurager\\Documentator\\Tests\\Fixtures'];
    expect(app(ResourceParser::class)->guessResourceClass('widgets', $namespaces))->toBe(WidgetResource::class)
        ->and(app(ResourceParser::class)->guessModelClass('widgets', $namespaces))->toBe(Widget::class)
        ->and(app(ResourceParser::class)->guessResourceClass('missing'))->toBeNull()
        ->and(app(ResourceParser::class)->guessModelClass('missing'))->toBeNull();
});

it('handles missing resources without executing them', function () {
    expect(app(ResourceParser::class)->parse('MissingResource'))->toBe([]);
});

it('discovers JsonResource subclasses without a Resource suffix', function () {
    expect(app(ResourceParser::class)->find(new ReflectionMethod(WidgetsController::class, 'view'), 'missing', []))->toBe(WidgetView::class);
});
