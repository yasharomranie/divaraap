<?php

declare(strict_types=1);

namespace Modules\User\App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Modules\User\App\Services\SmsIrOtpSender;
use Modules\User\App\Support\AuthenticationMethods;

final class PhoneOtpChallenge extends Model
{
    use SoftDeletes;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $guarded = [];

    protected $hidden = ['phone', 'phone_hash', 'session_hash', 'configuration_hash', 'code_hash', 'payload'];

    protected $casts = ['actor_id' => 'integer', 'phone' => 'encrypted', 'payload' => 'encrypted:array', 'expires_at' => 'datetime', 'consumed_at' => 'datetime', 'sent' => 'boolean'];

    public static function issue(string $phone, string $purpose, string $session, string $ip, array $payload, ?User $actor, ?string $previous): self
    {
        PhoneOtpLimit::reserve($phone, $ip, $session);
        if ($previous) {
            self::query()->whereKey($previous)->where('session_hash', self::digest($session))->update(['consumed_at' => now()]);
        }
        $id = (string) Str::uuid();
        $code = (string) random_int(100000, 999999);
        $challenge = self::query()->create([
            'id' => $id,
            'phone' => $phone,
            'phone_hash' => self::digest($phone),
            'session_hash' => self::digest($session),
            'configuration_hash' => app(AuthenticationMethods::class)->fingerprint(),
            'purpose' => $purpose,
            'actor_id' => $actor?->id,
            'code_hash' => self::digest($id.':'.$code),
            'payload' => $payload,
            'expires_at' => now()->addMinutes(5),
        ]);
        if (! app(SmsIrOtpSender::class)->send($phone, $code)) {
            $challenge->update(['consumed_at' => now()]);
            throw ValidationException::withMessages(['phone' => __('user::otp.send_failed')]);
        }
        $challenge->update(['sent' => true]);

        return $challenge;
    }

    public static function pending(?string $id, string $session, string $purpose, ?User $actor): ?self
    {
        return self::query()->whereKey($id)->where('session_hash', self::digest($session))->where('purpose', $purpose)
            ->where('actor_id', $actor?->id)->whereNull('consumed_at')->where('sent', true)->where('expires_at', '>', now())->first();
    }

    public static function verify(string $id, string $session, string $purpose, string $code, ?User $actor): User
    {
        $result = DB::transaction(function () use ($id, $session, $purpose, $code, $actor): ?User {
            $challenge = static::query()->whereKey($id)->lockForUpdate()->first();
            if (! $challenge || ! $challenge->sent || $challenge->consumed_at || $challenge->expires_at->lte(now())
                || $challenge->attempts >= 5 || $challenge->purpose !== $purpose || $challenge->actor_id !== $actor?->id
                || ! hash_equals($challenge->session_hash, self::digest($session))) {
                return null;
            }
            $challenge->attempts++;
            $challenge->save();
            if (! hash_equals($challenge->code_hash, self::digest($id.':'.$code))) {
                return null;
            }
            $challenge->update(['consumed_at' => now()]);
            $policy = app(AuthenticationMethods::class);
            if (! hash_equals($challenge->configuration_hash, $policy->fingerprint())) {
                return null;
            }
            if ($purpose === 'login') {
                $user = UserPhoneIdentity::forPhone($challenge->phone);

                return $user && $policy->canUseSms($user) ? $user : null;
            }
            if (UserPhoneIdentity::withTrashed()->where('phone', $challenge->phone)->exists()) {
                return null;
            }
            if ($purpose === 'bind' && (! $actor || ! $policy->canUseSms($actor) || UserPhoneIdentity::withTrashed()->where('user_id', $actor->id)->exists())) {
                return null;
            }
            try {
                return DB::transaction(function () use ($challenge, $purpose, $actor): User {
                    $user = $purpose === 'bind' ? $actor : User::registerFrom([
                        'name' => $challenge->payload['name'],
                        'email' => null,
                        'phone' => $challenge->phone,
                        'password' => Str::random(64),
                    ]);
                    UserPhoneIdentity::query()->create(['user_id' => $user->id, 'phone' => $challenge->phone, 'verified_at' => now()]);

                    return $user;
                });
            } catch (QueryException) {
                return null;
            }
        }, 3);
        if (! $result) {
            throw ValidationException::withMessages(['code' => __('user::otp.invalid_code')]);
        }

        return $result;
    }

    public function maskedPhone(): string
    {
        return '0'.substr($this->phone, 3, 2).'••••'.substr($this->phone, -4);
    }

    private static function digest(string $value): string
    {
        return hash_hmac('sha256', $value, config('app.key'));
    }
}
