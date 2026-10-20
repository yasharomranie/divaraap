<?php

declare(strict_types=1);

namespace Modules\Listing\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Modules\Listing\Models\Listing;

final class PublishScheduledListings
{
    public function handle(Request $request, Closure $next)
    {
        if ($request->isMethodSafe()) {
            try {
                Listing::publishDue();
            } catch (\Throwable $exception) {
                report($exception);
            }
        }

        return $next($request);
    }
}
