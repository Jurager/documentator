<?php

namespace Jurager\Documentator\Tests\Fixtures;

use Illuminate\Foundation\Http\FormRequest;
use RuntimeException;

class FailingRequest extends FormRequest
{
    public function rules(): array
    {
        throw new RuntimeException('Rules depend on unavailable request data.');
    }
}
