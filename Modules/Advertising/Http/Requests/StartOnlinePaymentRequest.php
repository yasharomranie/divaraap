<?php

declare(strict_types=1);

namespace Modules\Advertising\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Advertising\Services\PaymentGatewayCatalog;

final class StartOnlinePaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (string) $this->user()?->status === 'active';
    }

    public function rules(): array
    {
        return ['gateway' => ['required', 'string', Rule::in(PaymentGatewayCatalog::GATEWAYS)]];
    }
}
