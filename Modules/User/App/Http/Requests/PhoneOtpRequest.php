<?php

declare(strict_types=1);

namespace Modules\User\App\Http\Requests;

use App\Rules\IranianMobile;
use App\Support\IranianPhone;
use App\Support\PersianLocale;
use Illuminate\Foundation\Http\FormRequest;
use Modules\User\App\Support\AuthenticationMethods;

final class PhoneOtpRequest extends FormRequest
{
    public function authorize(): bool
    {
        $policy = app(AuthenticationMethods::class);
        $purpose = (string) $this->route('purpose');

        return $purpose === 'bind'
            ? $policy->allows('sms') && $this->user() && $policy->canUseSms($this->user())
            : $policy->allows('sms', $purpose === 'register' ? 'register' : 'login');
    }

    public function rules(): array
    {
        if ($this->routeIs('*.verify')) {
            return ['code' => ['required', 'string', 'regex:/\A[0-9]{6}\z/']];
        }
        if ($this->routeIs('*.resend')) {
            return [];
        }
        $rules = ['phone' => ['required', 'string', 'max:60', 'regex:/\A\+989[0-9]{9}\z/', new IranianMobile]];
        if ($this->route('purpose') === 'register') {
            $rules += ['first_name' => ['required', 'string', 'max:120'], 'last_name' => ['required', 'string', 'max:120'], 'terms' => ['accepted']];
        }
        if ($this->route('purpose') === 'bind') {
            $rules['current_password'] = ['required', 'current_password'];
        }

        return $rules;
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('phone') && is_string($this->input('phone'))) {
            $this->merge(['phone' => IranianPhone::normalize($this->input('phone'))]);
        }
        if ($this->has('code') && is_string($this->input('code'))) {
            $this->merge(['code' => PersianLocale::normalize(trim((string) $this->input('code')))]);
        }
    }
}
