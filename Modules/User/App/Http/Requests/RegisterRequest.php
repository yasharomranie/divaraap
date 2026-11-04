<?php

declare(strict_types=1);

namespace Modules\User\App\Http\Requests;

use App\Rules\IranianMobile;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Modules\User\App\Models\User;
use Modules\User\App\Support\AuthenticationMethods;

class RegisterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return app(AuthenticationMethods::class)->allows('email', 'register');
    }

    public function rules(): array
    {
        return [
            'first_name' => ['required', 'string', 'max:120'],
            'last_name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', Rule::unique((new User)->getTable(), 'email')],
            'password' => ['required', 'confirmed', Password::defaults()],
            'terms' => ['accepted'],
            'phone' => ['nullable', 'string', 'max:60', new IranianMobile],
            'marketing_opt_in' => ['nullable', 'boolean'],
        ];
    }

    public function fullName(): string
    {
        return trim($this->string('first_name')->toString().' '.$this->string('last_name')->toString());
    }
}
