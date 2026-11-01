<?php

use Illuminate\Database\Eloquent\Model;
use Jurager\Documentator\OpenApi\AutoParameterBuilder;
use Jurager\Documentator\Parsers\ResourceParser;
use Jurager\Documentator\Parsers\SourceParser;
use Jurager\Documentator\Tests\Fixtures\Widget;
use Jurager\Documentator\Tests\Fixtures\WidgetResource;

it('derives tree and relationship filters sort and valid includes', function () {
    $params = app(AutoParameterBuilder::class)->build(WidgetResource::class, new Widget);
    expect(array_column($params, 'name'))->toBe(['filter.parent_id', 'filter.owner.name', 'sort', 'include'])
        ->and($params[3]['description'])->toContain('owner', 'children', 'parent');
});

it('keeps includes but omits filters and sorting on single resource operations', function () {
    $params = app(AutoParameterBuilder::class)->build(WidgetResource::class, new Widget, false);
    expect(array_column($params, 'name'))->toBe(['include']);
});

it('ignores missing and unsupported model classes during resource resolution', function (?string $model) {
    expect(app(ResourceParser::class)->model($model))->toBeNull()
        ->and(app(AutoParameterBuilder::class)->build(null, null))->toBe([]);
})->with([null, 'MissingModel', stdClass::class, Model::class]);

it('keeps model field failures visible without aborting parameter generation', function () {
    $model = new class extends Widget
    {
        public function filterableFields(): array
        {
            throw new RuntimeException('Filter metadata unavailable');
        }
    };

    $parameters = app(AutoParameterBuilder::class)->build(WidgetResource::class, $model);

    expect(array_column($parameters, 'name'))->toContain('sort', 'include')
        ->and(implode('\n', app(SourceParser::class)->warnings()))->toContain('Filter metadata unavailable');
});
