<?php

declare(strict_types=1);

namespace Modules\Notification\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Notification\Models\EmailPreference;

final class EmailPreferenceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (string) $this->user()?->status === 'active';
    }

    public function rules(): array
    {
        return ['enabled' => ['required', 'boolean'], 'version' => ['required', 'integer', 'min:0'], 'consent' => ['nullable', 'boolean'], 'types' => ['nullable', 'array', 'max:7'], 'types.*' => ['string', 'distinct', Rule::in(EmailPreference::TYPES)]];
    }
}
