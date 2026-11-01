<?php

use Illuminate\Routing\Route;
use Jurager\Documentator\Parsers\ValidationParser;
use Jurager\Documentator\Tests\Fixtures\WidgetRequest;
use Jurager\Documentator\Tests\Fixtures\WidgetsController;

it('extracts FormRequest and inline array validation rules', function () {
    $form = new Route('POST', 'api/widgets', [WidgetsController::class, 'store']);
    $inline = new Route('POST', 'api/inline', [WidgetsController::class, 'inline']);
    expect(array_keys(app(ValidationParser::class)->parse(new ReflectionMethod(WidgetsController::class, 'store'), $form)))->toBe(array_keys((new WidgetRequest)->rules()))
        ->and(app(ValidationParser::class)->parse(new ReflectionMethod(WidgetsController::class, 'inline'), $inline))->toBe([
            'name' => ['required', 'string'], 'items' => 'required|array', 'items.*.id' => ['required', 'integer'],
        ]);
});

it('handles closure routes without request introspection', function () {
    expect(app(ValidationParser::class)->parse(null, new Route('GET', '/', fn () => [])))->toBe([]);
});
it('extracts rules from union FormRequest hints', function () {
    $route = new Route('POST', 'api/widgets', [WidgetsController::class, 'union']);
    expect(app(ValidationParser::class)->parse(new ReflectionMethod(WidgetsController::class, 'union'), $route))->toHaveKey('name');
});
