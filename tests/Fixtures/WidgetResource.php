<?php

namespace Jurager\Documentator\Tests\Fixtures;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class WidgetResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return ['id' => $this->id, 'name' => $this->name, 'price' => $this->price, 'is_active' => $this->is_active];
    }

    public function toRelationships(): array
    {
        return [
            'owner' => fn () => WidgetResource::make($this->owner),
            'children' => function () {
                return WidgetResource::collection($this->children);
            },
            'parent' => WidgetResource::class,
        ];
    }
}
