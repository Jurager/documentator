<?php

namespace Jurager\Documentator\Tests\Fixtures;

class CastedWidget extends Widget
{
    protected $keyType = 'string';

    protected $casts = [
        'score' => 'decimal:2',
        'enabled' => 'boolean',
        'settings' => 'object',
        'created_at' => 'datetime',
    ];
}
