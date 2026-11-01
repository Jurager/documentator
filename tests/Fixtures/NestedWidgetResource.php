<?php

namespace Jurager\Documentator\Tests\Fixtures;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class NestedWidgetResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $callback = function () {
            return ['ignored' => 'not a response field'];
        };

        return [
            'id' => $this->id,
            'rating' => $this->score,
            'flag' => $this->enabled,
            'options' => $this->settings,
            'created_at' => $this->created_at,
            'external_id' => (string) $this->id,
            'profile' => ['name' => 'Alice', 'active' => true],
            'entries' => [['value' => 1]],
            'empty' => [],
        ];
    }
}
