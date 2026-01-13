<?php

namespace Jurager\Documentator\Formats;

interface ResponseFormat
{
    /**
     * Get format name identifier.
     */
    public function name(): string;

    /**
     * Get format description for OpenAPI info.
     */
    public function description(): string;

    /**
     * Get base schemas for this format.
     *
     * @return array<string, mixed>
     */
    public function schemas(): array;

    /**
     * Get reusable response definitions.
     *
     * @return array<string, mixed>
     */
    public function responses(): array;

    /**
     * Build operation response schema based on HTTP method and resource.
     *
     * @param  string  $method  HTTP method (get, post, put, patch, delete)
     * @param  string  $resource  Resource name (e.g., 'users', 'posts')
     * @param  array<string, mixed>|null  $attributes  Request body attributes for response examples
     * @param  bool  $isCollection  Whether this is a collection endpoint
     * @return array<string, mixed>
     */
    public function operationResponse(string $method, string $resource, ?array $attributes = null, bool $isCollection = false): array;
}