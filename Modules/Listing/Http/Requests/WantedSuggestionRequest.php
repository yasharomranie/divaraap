<?php

declare(strict_types=1);

namespace Modules\Listing\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class WantedSuggestionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (string) $this->user()?->status === 'active';
    }

    public function rules(): array
    {
        return ['listing_id' => ['required', 'integer', 'min:1', 'max:999999999999999999'], 'revision' => ['required', 'integer', 'min:1']];
    }
}
