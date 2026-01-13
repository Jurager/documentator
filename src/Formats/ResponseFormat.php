<?php

namespace Jurager\Documentator\Formats;

interface ResponseFormat
{
    public function name(): string;

    public function description(): string;

    public function schemas(): array;

    public function responses(): array;
}
