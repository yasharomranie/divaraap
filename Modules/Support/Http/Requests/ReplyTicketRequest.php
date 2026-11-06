<?php

declare(strict_types=1);

namespace Modules\Support\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class ReplyTicketRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (string) $this->user()?->status === 'active';
    }

    public function rules(): array
    {
        return ['action' => ['required', 'string', Rule::in(['customer', 'closed', 'reopened'])], 'body' => ['required_unless:action,closed', 'nullable', 'string', 'max:4000'], 'version' => ['required', 'integer', 'min:0'], 'operation_key' => ['required', 'uuid'], 'confirmed' => $this->input('action') === 'closed' ? ['accepted'] : ['nullable']];
    }
}
