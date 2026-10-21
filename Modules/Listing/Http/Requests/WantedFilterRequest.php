<?php

declare(strict_types=1);

namespace Modules\Listing\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class WantedFilterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return ['search' => ['nullable', 'string', 'max:120'], 'category_id' => ['nullable', 'integer', 'min:1'], 'city_id' => ['nullable', 'integer', 'min:1']];
    }
}
