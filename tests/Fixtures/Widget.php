<?php

namespace Jurager\Documentator\Tests\Fixtures;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Widget extends Model
{
    public function filterableFields(): array
    {
        return ['name', 'active' => 'boolean', 'price' => ['gt'], 'parent_id' => ['eq', 'tree'], 'owner.name' => ['like']];
    }

    public function sortableFields(): array
    {
        return ['name', 'price'];
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(self::class);
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class);
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class);
    }
}
