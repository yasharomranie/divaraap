<?php

declare(strict_types=1);

namespace Modules\Admin\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Modules\Admin\Models\StaffSecurity;
use Modules\Admin\Models\StaffSecurityPolicy;
use Modules\User\App\Models\User;
use Symfony\Component\HttpFoundation\Response;

final class EnforceStaffSecurity
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->routeIs('staff-security.*', 'logout', 'filament.admin.auth.logout', 'payments.callback', 'notification-email.unsubscribe', 'notification-email.stop') || $request->is('lang/*')) {
            return $next($request);
        }
        $user = $request->user();
        $security = app(StaffSecurity::class);
        if (! $user instanceof User || ! $security->isStaff($user)) {
            return $next($request);
        }
        $policy = StaffSecurityPolicy::current();
        if (! $policy->requires($user)) {
            return $next($request);
        }
        $enrolled = $security->record($user)?->is_confirmed ?? false;
        $verified = $security->hasProof($user, $policy);
        if ($enrolled && $verified && ($request->isMethod('GET') || $request->isMethod('HEAD') || $security->recent($user, $policy))) {
            return $next($request);
        }
        $target = $enrolled ? route('staff-security.challenge') : route('staff-security.setup');
        if (! $request->hasSession() || $request->is('api/*')) {
            return response()->json(['message' => __('admin::security.verification_required'), 'verification_url' => $target], 403)->header('Cache-Control', 'private, no-store');
        }
        $path = $security->home($user);
        if ($request->isMethod('GET') && preg_match('#\A/(admin|panel)(/|\z)#', $request->getPathInfo())) {
            $path = $request->getRequestUri();
        } else {
            $referer = $request->headers->get('referer', '');
            if (is_string($referer) && parse_url($referer, PHP_URL_HOST) === $request->getHost()) {
                $candidate = parse_url($referer, PHP_URL_PATH);
                if (is_string($candidate) && preg_match('#\A/(admin|panel)(/|\z)#', $candidate)) {
                    $path = $candidate;
                }
            }
        }
        session(['staff_security.return_to' => substr($path, 0, 2000)]);

        return redirect($target)->header('Cache-Control', 'private, no-store');
    }
}
