<?php

declare(strict_types=1);

namespace Modules\Advertising\Http\Requests;

use App\Support\PersianLocale;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Advertising\Models\AdvertisingInvoice;

final class RefundRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (string) $this->user()?->status === 'active' && (! $this->routeIs('refunds.correct') || AdvertisingInvoice::billingAdmin());
    }

    public function rules(): array
    {
        if ($this->routeIs('refunds.withdraw')) {
            return ['version' => ['required', 'integer', 'min:0']];
        }
        $rules = ['amount' => ['required', 'string', 'regex:/\A[1-9][0-9]{0,12}\z/'], 'reason' => ['required', 'string', 'max:500'], $this->routeIs('refunds.correct') ? 'operation_key' : 'form_key' => ['required', 'uuid']];
        if ($this->routeIs('refunds.correct')) {
            $rules['confirmed'] = ['accepted'];
            $rules['direction'] = ['nullable', 'string', Rule::in(['decrease', 'increase'])];
        }

        return $rules;
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('amount'))) {
            $this->merge(['amount' => PersianLocale::normalize(trim($this->input('amount')))]);
        }
    }
}
