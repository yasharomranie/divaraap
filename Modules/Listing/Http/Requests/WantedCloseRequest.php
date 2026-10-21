<?php

declare(strict_types=1);

namespace Modules\Listing\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class WantedCloseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (string) $this->user()?->status === 'active';
    }

    public function rules(): array
    {
        return ['state' => ['required', Rule::in(['closed', 'fulfilled'])], 'revision' => ['required', 'integer', 'min:1']];
    }
}
