<?php

declare(strict_types=1);

namespace Modules\Admin\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use Modules\Admin\Http\Requests\StaffVerificationRequest;
use Modules\Admin\Models\StaffSecurity;
use Modules\Admin\Models\StaffSecurityPolicy;
use PragmaRX\Google2FA\Google2FA;

final class StaffSecurityController extends Controller
{
    public function setup(StaffSecurity $security): Response|RedirectResponse
    {
        $user = $security->actor();
        $source = $security->record($user);
        if ($source?->is_confirmed) {
            return $security->recent($user, StaffSecurityPolicy::current()) ? redirect($security->home($user)) : redirect()->route('staff-security.challenge');
        }
        $source = $security->pendingEnrollment($user);
        $qr = $source ? filament('filament-breezy')->getTwoFactorQrCodeSvg(app(Google2FA::class)->getQRCodeUrl(config('app.name'), $user->email ?: 'staff-'.$user->id, $source->two_factor_secret)) : null;

        return $this->page('setup', ['secret' => $source?->two_factor_secret, 'qr' => $qr]);
    }

    public function begin(StaffVerificationRequest $request, StaffSecurity $security): RedirectResponse
    {
        $security->beginEnrollment($request->validated('current_password'));

        return redirect()->route('staff-security.setup');
    }

    public function enroll(StaffVerificationRequest $request, StaffSecurity $security): RedirectResponse
    {
        $security->verify($request->validated('code'), null, true, $request);

        return redirect()->route('staff-security.recovery');
    }

    public function challenge(StaffSecurity $security): Response|RedirectResponse
    {
        $user = $security->actor();
        if (! $security->record($user)?->is_confirmed) {
            return redirect()->route('staff-security.setup');
        }
        $policy = StaffSecurityPolicy::current();

        return $this->page('challenge', ['needsPassword' => $security->hasProof($user, $policy) && ! $security->recent($user, $policy)]);
    }

    public function verify(StaffVerificationRequest $request, StaffSecurity $security): RedirectResponse
    {
        $security->verify($request->validated('code'), $request->validated('current_password'), false, $request);
        $user = $security->actor();
        $home = $security->home($user);
        $path = session()->pull('staff_security.return_to', $home);
        if ($path === '/admin') {
            $path = $home;
        }

        return redirect(is_string($path) && preg_match('#\A/(admin|panel)(/|\z)#', $path) ? $path : $home);
    }

    public function recovery(StaffSecurity $security): Response|RedirectResponse
    {
        $user = $security->actor();
        $source = $security->record($user);
        if (! $security->recent($user, StaffSecurityPolicy::current()) || ! $source || session()->pull('staff_security.show_recovery') !== (int) $source->id) {
            return redirect($security->home($user));
        }

        return $this->page('recovery', ['codes' => $source->two_factor_recovery_codes]);
    }

    private function page(string $view, array $data): Response
    {
        return response()->view('admin::staff-security.'.$view, $data)->header('Cache-Control', 'private, no-store')->header('Referrer-Policy', 'no-referrer')->header('X-Robots-Tag', 'noindex, nofollow');
    }
}
