<?php

declare(strict_types=1);

namespace Modules\Advertising\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class AdvertisingEventRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return ['choice' => ['nullable', 'string', 'regex:/^[a-f0-9]{64}$/'], 'listing' => ['nullable', 'integer', 'min:1', 'max:999999999999999999'], 'token' => ['required', 'string', 'max:6000'], 'kind' => ['required', 'in:impression,click'], 'device' => ['required', 'in:mobile,desktop']];
    }
}
