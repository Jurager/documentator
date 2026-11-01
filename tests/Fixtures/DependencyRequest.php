<?php

namespace Jurager\Documentator\Tests\Fixtures;

use Illuminate\Foundation\Http\FormRequest;
use Jurager\Documentator\OpenApi\TypeResolver;

class DependencyRequest extends FormRequest
{
    public function rules(TypeResolver $types): array
    {
        return ['enabled' => ['required', $types->fromFieldName('is_enabled')]];
    }
}
