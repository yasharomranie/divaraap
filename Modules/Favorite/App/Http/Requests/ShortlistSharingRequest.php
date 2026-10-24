<?php

declare(strict_types=1);

namespace Modules\Favorite\App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class ShortlistSharingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (string) $this->user()?->status === 'active';
    }

    public function rules(): array
    {
        return ['action' => ['required', Rule::in(['create', 'refresh', 'rotate', 'revoke', 'clear'])], 'share_revision' => ['required', 'integer', 'min:0'], 'consent' => ['required_if:action,create,refresh', 'accepted_if:action,create,refresh'], 'signature' => ['required_if:action,create,refresh', 'nullable', 'string', 'regex:/^[a-f0-9]{64}$/D']];
    }

    public function attributes(): array
    {
        return ['consent' => __('favorite::sharing.consent')];
    }
}
