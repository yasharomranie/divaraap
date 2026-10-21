<?php

declare(strict_types=1);

namespace Modules\Advertising\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Modules\Advertising\Models\AdvertisingOrder;

final class AdvertisingOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (string) $this->user()?->status === 'active';
    }

    public function rules(): array
    {
        return AdvertisingOrder::requestRules();
    }
}
