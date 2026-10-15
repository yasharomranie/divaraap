<?php

declare(strict_types=1);

namespace App\Rules;

use App\Support\Money;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

final class MonetaryAmount implements ValidationRule
{
    public function __construct(private readonly string $currency = 'IRR') {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! Money::valid($value, $this->currency)) {
            $fail(app()->getLocale() === 'fa'
                ? ($this->currency === 'IRR' ? 'مبلغ ریالی باید عدد صحیح نامنفی و حداکثر ۱۸ رقم باشد.' : 'مبلغ باید عدد نامنفی با حداکثر ۱۸ رقم و دو رقم اعشار باشد.')
                : ($this->currency === 'IRR' ? 'Enter a non-negative whole rial amount with at most 18 digits.' : 'Enter a non-negative amount with at most 18 digits and two decimal places.'));
        }
    }
}
