<?php

declare(strict_types=1);

namespace Modules\User\App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Site\App\Settings\AuthenticationSettings;

final class PhoneOtpLimit extends Model
{
    use SoftDeletes;

    protected $primaryKey = 'key';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $guarded = [];

    protected $casts = ['expires_at' => 'datetime'];

    public static function reserve(string $phone, string $ip, string $session): void
    {
        $limits = [
            ['phone:'.$phone, 1, 60],
            ['phone-hour:'.$phone, 5, 3600],
            ['ip-hour:'.$ip, 20, 3600],
            ['ip-day:'.$ip, 100, 86400],
            ['session:'.$session, 10, 3600],
            ['site-day', app(AuthenticationSettings::class)->sms_daily_limit, 86400],
        ];
        $buckets = [];
        foreach ($limits as [$name, $max, $seconds]) {
            $key = hash_hmac('sha256', $name, config('app.key'));
            $buckets[$key] = [$max, $seconds];
        }
        ksort($buckets);
        DB::transaction(function () use ($buckets): void {
            foreach ($buckets as $key => [$max, $seconds]) {
                static::query()->insertOrIgnore(['key' => $key, 'hits' => 0, 'expires_at' => now()->addSeconds($seconds), 'created_at' => now(), 'updated_at' => now()]);
                $bucket = static::query()->whereKey($key)->lockForUpdate()->firstOrFail();
                if ($bucket->expires_at->lte(now())) {
                    $bucket->hits = 0;
                    $bucket->expires_at = now()->addSeconds($seconds);
                }
                if ($bucket->hits >= $max) {
                    throw ValidationException::withMessages(['phone' => __('user::otp.rate_limited')]);
                }
                $bucket->hits++;
                $bucket->save();
            }
        }, 3);
        self::query()->where('expires_at', '<', now()->subDays(7))->forceDelete();
        PhoneOtpChallenge::query()->where('expires_at', '<', now()->subDay())->forceDelete();
    }
}
