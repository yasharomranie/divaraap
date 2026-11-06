<?php

declare(strict_types=1);

namespace Modules\Support\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Support\Models\SupportTicket;

final class CreateTicketRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (string) $this->user()?->status === 'active';
    }

    public function rules(): array
    {
        return ['subject' => ['required', 'string', 'max:150'], 'category' => ['required', 'string', Rule::in(SupportTicket::CATEGORIES)], 'body' => ['required', 'string', 'max:4000'], 'form_key' => ['required', 'uuid']];
    }
}
