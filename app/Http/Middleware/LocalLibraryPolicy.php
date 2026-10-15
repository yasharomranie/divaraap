<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class LocalLibraryPolicy
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);
        if (config('hosting.profile') === 'shared' && str_contains($response->headers->get('Content-Type', ''), 'text/html')) {
            $response->headers->set('Content-Security-Policy', "script-src 'self' 'unsafe-inline' 'unsafe-eval'; style-src 'self' 'unsafe-inline'; font-src 'self' data:; worker-src 'self' blob:; object-src 'none'; base-uri 'self'");
        }

        return $response;
    }
}
