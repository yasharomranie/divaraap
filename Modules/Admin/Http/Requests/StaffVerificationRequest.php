<?php

declare(strict_types=1);

namespace Modules\Admin\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Modules\Admin\Models\StaffSecurity;

final class StaffVerificationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() && app(StaffSecurity::class)->isStaff($this->user());
    }

    public function rules(): array
    {
        return $this->routeIs('staff-security.begin') ? ['current_password' => ['required', 'string', 'max:1024']] : ['code' => ['required', 'string', 'max:80'], 'current_password' => ['nullable', 'string', 'max:1024']];
    }
}
