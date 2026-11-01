<?php

namespace Jurager\Documentator\Tests\Fixtures;

use Jurager\Documentator\Formats\SimpleFormat;
use Jurager\Documentator\OpenApi\TypeResolver;

class DependencyFormat extends SimpleFormat
{
    public function __construct(public TypeResolver $types) {}
}
