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
        return ['token' => ['required', 'string', 'max:6000'], 'kind' => ['required', 'in:impression,click'], 'device' => ['required', 'in:mobile,desktop']];
    }
}
