<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Support\IranianPhone;
use App\Support\PersianLocale;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class NormalizeLocalizedNumbers
{
    public function handle(Request $request, Closure $next): Response
    {
        foreach (['price', 'amount', 'min_price', 'max_price', 'phone', 'contact_phone'] as $key) {
            $value = $request->input($key);

            if (is_string($value)) {
                $request->merge([$key => in_array($key, ['phone', 'contact_phone'], true) ? IranianPhone::normalize($value) : PersianLocale::normalize($value)]);
            }
        }

        return $next($request);
    }
}
