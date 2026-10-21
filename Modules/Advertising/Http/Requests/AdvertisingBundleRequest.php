<?php

declare(strict_types=1);

namespace Modules\Advertising\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class AdvertisingBundleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (string) $this->user()?->status === 'active';
    }

    public function rules(): array
    {
        return ['request_key' => ['required', 'uuid'], 'brief' => ['required', 'string', 'max:2000'], 'version' => ['required', 'string', 'size:64', 'regex:/^[a-f0-9]{64}$/D']];
    }
}
