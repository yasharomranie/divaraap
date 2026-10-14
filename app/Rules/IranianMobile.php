<?php

declare(strict_types=1);

namespace App\Rules;

use App\Support\IranianPhone;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

final class IranianMobile implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (is_string($value) && IranianPhone::isIranian($value) && ! IranianPhone::valid($value)) {
            $fail(app()->getLocale() === 'fa' ? 'شمارهٔ موبایل ایران باید ۱۱ رقم و با ۰۹ شروع شود؛ قالب ‎+98 نیز پذیرفته می‌شود.' : 'Enter a valid Iranian mobile number starting with 09 or +989.');
        }
    }
}
