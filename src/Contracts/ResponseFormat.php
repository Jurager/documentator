<?php

namespace Jurager\Documentator\Contracts;

interface ResponseFormat
{
    public function schemas(): array;

    public function responses(): array;

    /** @return array<string, mixed> OpenAPI response fields, without the HTTP status. */
    public function response(string $resource, array $data = [], bool $collection = false): array;
}
