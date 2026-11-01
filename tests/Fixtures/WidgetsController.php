<?php

namespace Jurager\Documentator\Tests\Fixtures;

use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Http\Request;
use Jurager\Documentator\Tests\Fixtures\WidgetResource as AliasedResource;

class WidgetsController
{
    use ValidatesRequests;

    /**
     * List widgets.
     * A paginated list of widgets.
     *
     * @group Widgets
     *
     * @resource widgets
     *
     * @preset pagination
     *
     * @queryParam filter.name string optional Filter by name. Example: "desk"
     *
     * @header X-Tenant integer required Tenant identifier.
     */
    public function index()
    {
        return AliasedResource::collection([]);
    }

    /**
     * @urlParam widget integer optional Widget identifier. Example: 42
     */
    public function show(Widget $widget): AliasedResource
    {
        return AliasedResource::make($widget);
    }

    public function store(WidgetRequest $request): AliasedResource
    {
        return new AliasedResource(null);
    }

    public function update(WidgetRequest $request, Widget $widget): AliasedResource
    {
        return new AliasedResource($widget);
    }

    public function destroy(Widget $widget): void {}

    /**
     * @unauthenticated
     *
     * @deprecated
     *
     * @response 202 {"queued":true}
     * @response 400 {"message":"Invalid"}
     */
    public function queue(): void {}

    /**
     * @bodyParam attachments file[] required Uploaded files.
     * @bodyParam profile.avatar file optional Avatar.
     */
    public function upload(): void {}

    public function inline(Request $request): void
    {
        $request->validate([
            'name' => ['required', 'string'],
            'items' => 'required|array',
            'items.*.id' => ['required', 'integer'],
        ]);
    }

    /**
     * @queryParam filter.name integer optional Different filter.
     *
     * @header X-Tenant string optional Another tenant.
     */
    public function inconsistent(): void {}

    public function aliased()
    {
        return AliasedResource::make(null);
    }

    public function bound(Widget $widget): void {}

    /**
     * @queryParam ids integer[] optional Identifiers. Example: [1,2]
     * @queryParam filter.price number optional Price threshold.
     *
     * @header X-Enabled boolean optional Enabled. Example: false
     *
     * @response 204 null
     */
    public function parameters(): void {}

    /**
     * @bodyParam profile.avatar file optional Avatar.
     */
    public function nestedUpload(): void {}

    /**
     * @urlParam widget integer required Widget identifier.
     */
    public function pathOnly(Widget $widget): void {}

    public function union(Request|WidgetRequest $request, WidgetRequest $input): void {}

    /**
     * @bodyParam settings object optional Settings. Example: {}
     *
     * @response 200 {}
     * @response 201 []
     * @response 202 true
     * @response 400 0
     * @response 404 null
     * @response 500 "failed"
     */
    public function examples(): void {}

    public function nested(): NestedWidgetResource
    {
        return new NestedWidgetResource(null);
    }

    public function dependency(DependencyRequest $request): void {}

    /** @bodyParam name string optional Manually documented field. */
    public function failing(FailingRequest $request): void {}

    /**
     * @preset pagination
     *
     * @queryParam filter[parent_id] integer optional Explicit parent filter.
     * @queryParam page.size integer optional Explicit page size.
     */
    public function overrides(): AliasedResource
    {
        return new AliasedResource(null);
    }

    /** @queryParam ids integer[] optional Identifiers. */
    public function integerIds(): void {}

    /** @queryParam ids string[] optional Identifiers. */
    public function stringIds(): void {}

    /** @header X-Tenant integer optional Tenant. */
    public function headerUpper(): void {}

    /** @header x-tenant string optional Tenant. */
    public function headerLower(): void {}

    public function view()
    {
        return WidgetView::make(null);
    }

    public function boundCasted(CastedWidget $widget): WidgetView
    {
        return new WidgetView($widget);
    }

    public function scopedValidation(Request $request, object $validator): void
    {
        $callback = function (Request $input) {
            $input->validate(['nested' => 'required|string']);
        };
        $validator->validate(['unrelated' => 'required|string']);
        $request->validate(['name' => ['required', 'string']]);
        $this->validate($request, ['count' => 'required|integer']);
    }

    public function dynamicValidation(Request $request): void
    {
        $request->validate(['name' => $this->rules()]);
    }
}
