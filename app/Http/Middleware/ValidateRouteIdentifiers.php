<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class ValidateRouteIdentifiers
{
    public function handle(Request $request, Closure $next): Response
    {
        foreach (['listing', 'seller', 'conversation', 'offer', 'video', 'notification', 'favoriteSearch'] as $parameter) {
            $value = $request->route($parameter);
            if (! is_string($value)) {
                continue;
            }
            $number = ltrim($value, '0');
            if ($number === '' || ! ctype_digit($number) || strlen($number) > 19 || (strlen($number) === 19 && strcmp($number, '9223372036854775807') > 0)) {
                abort(404);
            }
            $request->route()->setParameter($parameter, $number);
        }

        return $next($request);
    }
}
