<?php

declare(strict_types=1);

namespace Modules\Panel\App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Listing\Models\Listing;

class UpdateListingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'price' => ['nullable', 'numeric', 'min:0'],
            'status' => ['required', Rule::in(array_keys(Listing::panelStatusOptions()))],
            'contact_phone' => ['nullable', 'string', 'max:60'],
            'contact_email' => ['nullable', 'email', 'max:255'],
            'country' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:255'],
            'expires_at' => ['nullable', 'date'],
        ];
    }

    public function messages(): array
    {
        return [
            'title.required' => __('panel::messages.required_title'),
            'price.numeric' => __('panel::messages.numeric_price'),
            'status.required' => __('panel::messages.required_status'),
            'status.in' => __('panel::messages.invalid_status'),
            'contact_email.email' => __('panel::messages.invalid_email'),
            'expires_at.date' => __('panel::messages.invalid_expiry'),
        ];
    }
}
