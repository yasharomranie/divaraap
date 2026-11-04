<?php

declare(strict_types=1);

namespace Modules\User\App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Support\IranianPhone;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Modules\User\App\Http\Requests\PhoneOtpRequest;
use Modules\User\App\Models\PhoneOtpChallenge;
use Modules\User\App\Models\UserPhoneIdentity;
use Modules\User\App\Support\AuthenticationMethods;
use Modules\User\App\Support\AuthRedirector;

final class PhoneOtpController extends Controller
{
    public function create(Request $request, string $purpose): Response
    {
        $this->assertAllowed($request, $purpose);
        app(AuthRedirector::class)->rememberQueryTarget($request);
        $challenge = PhoneOtpChallenge::pending($request->session()->get('phone_otp.id'), $this->nonce($request), $purpose, $request->user());

        return response()->view('user::auth.phone', [
            'purpose' => $purpose,
            'challenge' => $challenge,
            'identity' => $request->user() ? UserPhoneIdentity::forUser($request->user()) : null,
            'phoneUrl' => $this->url($purpose),
        ])->header('Cache-Control', 'private, no-store');
    }

    public function send(PhoneOtpRequest $request, string $purpose): RedirectResponse
    {
        $this->assertAllowed($request, $purpose);
        $payload = $purpose === 'register' ? ['name' => trim($request->string('first_name').' '.$request->string('last_name')), 'terms_at' => now()->toIso8601String()] : [];
        $challenge = PhoneOtpChallenge::issue(IranianPhone::normalize($request->string('phone')->toString()), $purpose, $this->nonce($request), (string) $request->ip(), $payload, $request->user(), $request->session()->get('phone_otp.id'));
        $request->session()->put('phone_otp.id', $challenge->id);

        return redirect($this->url($purpose))->with('success', __('user::otp.sent'));
    }

    public function resend(PhoneOtpRequest $request, string $purpose): RedirectResponse
    {
        $this->assertAllowed($request, $purpose);
        $old = PhoneOtpChallenge::pending($request->session()->get('phone_otp.id'), $this->nonce($request), $purpose, $request->user());
        abort_unless($old, 422);
        $challenge = PhoneOtpChallenge::issue($old->phone, $purpose, $this->nonce($request), (string) $request->ip(), $old->payload ?? [], $request->user(), $old->id);
        $request->session()->put('phone_otp.id', $challenge->id);

        return redirect($this->url($purpose))->with('success', __('user::otp.sent'));
    }

    public function verify(PhoneOtpRequest $request, string $purpose): RedirectResponse
    {
        $this->assertAllowed($request, $purpose);
        $user = PhoneOtpChallenge::verify((string) $request->session()->get('phone_otp.id'), $this->nonce($request), $purpose, $request->string('code')->toString(), $request->user());
        $request->session()->forget('phone_otp');
        $request->session()->regenerate();
        if ($purpose === 'bind') {
            return redirect()->route('panel.profile.edit')->with('success', __('user::otp.bound'));
        }
        Auth::guard('web')->login($user);

        return redirect()->intended(route('dashboard', absolute: false));
    }

    public function reset(Request $request, string $purpose): RedirectResponse
    {
        $this->assertAllowed($request, $purpose);
        $id = $request->session()->pull('phone_otp.id');
        if ($id && ($challenge = PhoneOtpChallenge::pending($id, $this->nonce($request), $purpose, $request->user()))) {
            $challenge->update(['consumed_at' => now()]);
        }

        return redirect($this->url($purpose));
    }

    private function assertAllowed(Request $request, string $purpose): void
    {
        $policy = app(AuthenticationMethods::class);
        abort_unless(in_array($purpose, ['login', 'register', 'bind'], true), 404);
        abort_unless($policy->allows('sms', $purpose === 'register' ? 'register' : 'login'), 403);
        if ($purpose === 'bind') {
            abort_unless($request->user() && $policy->canUseSms($request->user()), 403);
        }
    }

    private function nonce(Request $request): string
    {
        if (! $request->session()->has('phone_otp.nonce')) {
            $request->session()->put('phone_otp.nonce', Str::random(64));
        }

        return $request->session()->get('phone_otp.nonce');
    }

    private function url(string $purpose): string
    {
        return $purpose === 'bind' ? route('phone.bind') : route('phone.create', ['purpose' => $purpose]);
    }
}
