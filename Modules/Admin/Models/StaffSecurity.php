<?php

declare(strict_types=1);

namespace Modules\Admin\Models;

use App\Support\PersianLocale;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Jeffgreco13\FilamentBreezy\Models\BreezySession;
use Modules\Notification\Models\UserNotification;
use Modules\User\App\Models\User;
use PragmaRX\Google2FA\Google2FA;

final class StaffSecurity extends Model
{
    use SoftDeletes;

    protected $table = 'staff_security_states';

    protected $guarded = ['*'];

    public function home(User $user): string
    {
        return $user->hasRole('admin') ? '/admin' : '/admin/operations';
    }

    public function actor(): User
    {
        $actor = auth()->id() ? User::find(auth()->id()) : null;
        abort_unless($actor && $this->isStaff($actor), 403);

        return $actor;
    }

    public function isStaff(User $user): bool
    {
        return (string) $user->status === 'active' && ($user->hasRole(['admin', 'support', 'moderator', 'advertising-manager']) || $user->can('admin-panel.access'));
    }

    public function record(User $user, bool $lock = false): ?BreezySession
    {
        return $user->breezySessions()->withoutGlobalScopes()->where('panel_id', 'admin')->latest('id')->when($lock, fn ($query) => $query->lockForUpdate())->first();
    }

    public function sourceHash(User $user): ?string
    {
        $source = $this->record($user);
        if (! $source?->is_confirmed) {
            return null;
        }

        return hash_hmac('sha256', $source->id.'|'.$source->two_factor_secret.'|'.$user->password, config('app.key'));
    }

    public function hasProof(User $user, ?StaffSecurityPolicy $policy = null): bool
    {
        if (! request()->hasSession()) {
            return false;
        }
        $policy ??= StaffSecurityPolicy::current();
        $proof = session('staff_security.proof');
        $hash = $this->sourceHash($user);

        return $hash && is_array($proof) && ($proof['user'] ?? null) === (int) $user->id && ($proof['version'] ?? null) === $policy->version
            && is_int($proof['at'] ?? null) && $proof['at'] <= now()->timestamp && $proof['at'] >= now()->subHours(12)->timestamp
            && hash_equals($hash, (string) ($proof['source'] ?? ''));
    }

    public function recent(User $user, StaffSecurityPolicy $policy): bool
    {
        return $this->hasProof($user, $policy) && session('staff_security.proof.at', 0) >= now()->subMinutes($policy->step_up_minutes)->timestamp;
    }

    public function bindProof(User $user, StaffSecurityPolicy $policy, ?int $verifiedAt = null): void
    {
        $hash = $this->sourceHash($user);
        abort_unless($hash && request()->hasSession(), 403);
        $source = $this->record($user);
        session()->regenerate(true);
        session(['staff_security.proof' => ['user' => (int) $user->id, 'version' => $policy->version, 'source' => $hash, 'at' => $verifiedAt ?? now()->timestamp], 'breezy_session_id' => md5((string) $source->id)]);
    }

    public function pendingEnrollment(User $user): ?BreezySession
    {
        $source = $this->record($user);
        $capability = request()->hasSession() ? session('staff_security.enrollment') : null;
        if (! $source || $source->is_confirmed || ! is_array($capability) || ($capability['user'] ?? null) !== (int) $user->id || ($capability['source'] ?? null) !== (int) $source->id || ($capability['at'] ?? 0) < now()->subMinutes(10)->timestamp) {
            return null;
        }

        return hash_equals(hash_hmac('sha256', $source->two_factor_secret, config('app.key')), (string) ($capability['hash'] ?? '')) ? $source : null;
    }

    public function beginEnrollment(string $password): void
    {
        $actor = $this->actor();
        $error = DB::transaction(function () use ($actor, $password): ?string {
            $user = User::query()->whereKey($actor->id)->lockForUpdate()->firstOrFail();
            $state = $this->state($user);
            if ($state->locked_until && now()->lt($state->locked_until)) {
                return 'locked';
            }
            if (! Hash::check($password, $user->password)) {
                $this->failure($state);

                return 'password_invalid';
            }
            $record = $this->record($user, true);
            if ($record?->is_confirmed) {
                return 'already_enrolled';
            }
            if ($record) {
                $record->delete();
            }
            $user->breezySessions()->create(['panel_id' => 'admin', 'two_factor_secret' => app(Google2FA::class)->generateSecretKey(), 'two_factor_recovery_codes' => $user->generateRecoveryCodes()]);
            DB::table('staff_security_states')->where('id', $state->id)->update(['source_hash' => null, 'last_totp_step' => null, 'failures' => 0, 'locked_until' => null, 'updated_at' => now()]);
            session()->forget('staff_security.proof');

            return null;
        }, 3);
        if ($error) {
            throw ValidationException::withMessages(['current_password' => __('admin::security.'.$error)]);
        }
        $source = $this->record($actor);
        session(['staff_security.enrollment' => ['user' => (int) $actor->id, 'source' => (int) $source->id, 'hash' => hash_hmac('sha256', $source->two_factor_secret, config('app.key')), 'at' => now()->timestamp]]);
    }

    public function verify(string $code, ?string $password, bool $enrollment, Request $request): void
    {
        $actor = $this->actor();
        $code = trim(PersianLocale::normalize($code));
        $verifiedSource = null;
        $error = DB::transaction(function () use ($actor, $code, $password, $enrollment, $request, &$verifiedSource): ?string {
            $user = User::query()->whereKey($actor->id)->lockForUpdate()->firstOrFail();
            $state = $this->state($user);
            if ($state->locked_until && now()->lt($state->locked_until)) {
                return 'locked';
            }
            $source = $this->record($user, true);
            $policy = StaffSecurityPolicy::current();
            $needsPassword = ! $enrollment && $this->hasProof($user, $policy) && ! $this->recent($user, $policy);
            if ($needsPassword && (! is_string($password) || ! Hash::check($password, $user->password))) {
                $this->failure($state);

                return 'password_invalid';
            }
            if (! $source || $enrollment && ! $this->pendingEnrollment($user) || $enrollment === (bool) $source->is_confirmed) {
                return 'invalid';
            }
            $secretHash = hash_hmac('sha256', $source->two_factor_secret, config('app.key'));
            $last = $state->source_hash === $secretHash ? $state->last_totp_step : null;
            $step = preg_match('/\A[0-9]{6}\z/', $code) ? app(Google2FA::class)->verifyKeyNewer($source->two_factor_secret, $code, $last === null ? 0 : (int) $last) : false;
            $recovery = false;
            if ($step === false && ! $enrollment) {
                foreach ($source->two_factor_recovery_codes ?? [] as $candidate) {
                    if (hash_equals($candidate, $code)) {
                        $recovery = true;
                        $source->two_factor_recovery_codes = array_values(array_filter($source->two_factor_recovery_codes, fn ($value) => ! hash_equals($value, $code)));
                        $source->save();
                        break;
                    }
                }
            }
            if ($step === false && ! $recovery) {
                $this->failure($state);

                return 'code_invalid';
            }
            if ($enrollment) {
                $source->two_factor_confirmed_at = now();
                $source->save();

            }
            DB::table('staff_security_states')->where('id', $state->id)->update(['source_hash' => $secretHash, 'last_totp_step' => $step === false ? $last : $step, 'failures' => 0, 'locked_until' => null, 'updated_at' => now()]);
            $verifiedSource = $this->sourceHash($user);
            $this->context($user, $request);
            DB::table('staff_security_events')->insert(['user_id' => $user->id, 'kind' => $enrollment ? 'enrolled' : 'verified', 'created_at' => now(), 'updated_at' => now()]);

            return null;
        }, 3);
        if ($error) {
            throw ValidationException::withMessages([$error === 'password_invalid' ? 'current_password' : 'code' => __('admin::security.'.$error)]);
        }
        $user = $this->actor();
        if (! $verifiedSource || ! hash_equals($verifiedSource, (string) $this->sourceHash($user))) {
            throw ValidationException::withMessages(['code' => __('admin::security.code_invalid')]);
        }
        $this->bindProof($user, StaffSecurityPolicy::current());
        if ($enrollment) {
            session()->forget('staff_security.enrollment');
            session(['staff_security.show_recovery' => (int) $this->record($user)->id]);
        }
    }

    private function state(User $user): object
    {
        DB::table('staff_security_states')->insertOrIgnore(['user_id' => $user->id, 'failures' => 0, 'created_at' => now(), 'updated_at' => now()]);

        $state = DB::table('staff_security_states')->where('user_id', $user->id)->lockForUpdate()->first();
        if ($state->locked_until && now()->gte($state->locked_until)) {
            DB::table('staff_security_states')->where('id', $state->id)->update(['failures' => 0, 'locked_until' => null, 'updated_at' => now()]);
            $state->failures = 0;
            $state->locked_until = null;
        }

        return $state;
    }

    private function failure(object $state): void
    {
        $failures = $state->failures + 1;
        DB::table('staff_security_states')->where('id', $state->id)->update(['failures' => $failures, 'locked_until' => $failures >= 5 ? now()->addMinutes(10) : null, 'updated_at' => now()]);
    }

    private function context(User $user, Request $request): void
    {
        $device = $request->cookie('staff_security_device');
        if (! is_string($device) || ! preg_match('/\A[A-Za-z0-9]{64}\z/', $device)) {
            $device = Str::random(64);
            Cookie::queue(cookie('staff_security_device', $device, 60 * 24 * 30, '/', null, $request->isSecure(), true, false, 'lax'));
        }
        $ip = inet_pton((string) $request->ip());
        $prefix = $ip === false ? 'unknown' : substr($ip, 0, strlen($ip) === 4 ? 3 : 7);
        $hash = hash_hmac('sha256', $device.'|'.substr((string) $request->userAgent(), 0, 1024).'|'.$prefix, config('app.key'));
        $known = DB::table('staff_security_contexts')->where('user_id', $user->id)->where('fingerprint', $hash)->lockForUpdate()->first();
        $recentKnown = DB::table('staff_security_contexts')->where('user_id', $user->id)->where('last_seen_at', '>=', now()->subDays(30))->exists();
        if ($known) {
            DB::table('staff_security_contexts')->where('id', $known->id)->update(['last_seen_at' => now(), 'updated_at' => now()]);
        } else {
            DB::table('staff_security_contexts')->insert(['user_id' => $user->id, 'fingerprint' => $hash, 'last_seen_at' => now(), 'created_at' => now(), 'updated_at' => now()]);
        }
        if ($recentKnown && (! $known || $known->last_seen_at < now()->subDays(30)->toDateTimeString())) {
            DB::table('staff_security_events')->insert(['user_id' => $user->id, 'kind' => 'new_context', 'created_at' => now(), 'updated_at' => now()]);
            UserNotification::publish($user->id, 'security', __('admin::security.new_context'), null, url('/admin/my-profile'));
        }
    }

    public function recover(int $userId): void
    {
        abort_unless(app()->runningInConsole(), 403);
        DB::transaction(function () use ($userId): void {
            $user = User::query()->whereKey($userId)->lockForUpdate()->firstOrFail();
            abort_unless($this->isStaff($user), 403);
            $user->breezySessions()->withoutGlobalScopes()->where('panel_id', 'admin')->delete();
            DB::table('staff_security_states')->where('user_id', $user->id)->update(['source_hash' => null, 'last_totp_step' => null, 'failures' => 0, 'locked_until' => null, 'updated_at' => now()]);
            $user->forceFill(['remember_token' => Str::random(60)])->save();
            DB::table('staff_security_events')->insert(['user_id' => $user->id, 'kind' => 'operator_reset', 'created_at' => now(), 'updated_at' => now()]);
        }, 3);
    }
}
