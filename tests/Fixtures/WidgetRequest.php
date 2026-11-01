<?php

namespace Jurager\Documentator\Tests\Fixtures;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class WidgetRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'widget' => ['required', 'integer'],
            'name' => ['required', 'string', 'min:2', 'max:50'],
            'price' => ['nullable', 'numeric', 'min:0.25', 'max:99.95'],
            'tags' => ['array', 'min:1', 'max:3'],
            'tags.*' => ['string', Rule::in(['red', 'blue'])],
            'settings.enabled' => ['required', 'boolean'],
            'rows.*.cells.*.value' => ['required', 'integer'],
            'email' => ['email', Rule::unique('widgets', 'email')],
        ];
    }
}
