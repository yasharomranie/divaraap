<?php

declare(strict_types=1);

namespace Modules\Notification\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;
use Modules\Notification\Services\NotificationEmailSender;
use Modules\Site\App\Settings\EmailNotificationSettings;
use Modules\User\App\Models\User;
use Throwable;

final class EmailDelivery extends Model
{
    use SoftDeletes;

    protected $table = 'notification_email_deliveries';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['preference_version' => 'integer', 'started_at' => 'datetime', 'accepted_at' => 'datetime'];
    }

    public static function monitoringSummary(int $ageMinutes, int $windowMinutes): array
    {
        return [
            'backlog' => self::query()->where(function ($query) use ($ageMinutes): void {
                $query->where(fn ($pending) => $pending->where('state', 'pending')->where('created_at', '<', now()->subMinutes($ageMinutes)))
                    ->orWhere(fn ($processing) => $processing->where('state', 'processing')->where('started_at', '<', now()->subMinutes(10)));
            })->count(),
            'failures' => self::query()->whereIn('state', ['failed', 'uncertain'])->where('updated_at', '>=', now()->subMinutes($windowMinutes))->count(),
        ];
    }

    public static function enqueue(UserNotification $notification): void
    {
        $settings = app(EmailNotificationSettings::class);
        if (! $settings->enabled || ! NotificationEmailSender::ready() || ! in_array($notification->type, EmailPreference::TYPES, true) || $notification->read_at) {
            return;
        }
        $preference = EmailPreference::query()->where('user_id', $notification->user_id)->first();
        if (! $preference || ! $preference->matchesUser(User::find($notification->user_id)) || ! in_array($notification->type, $preference->types, true)) {
            return;
        }
        self::query()->firstOrCreate(['notification_id' => $notification->id], ['preference_id' => $preference->id, 'preference_version' => $preference->version, 'state' => 'pending']);
    }

    public static function deliverBatch(int $limit = 50): int
    {
        $limit = max(1, min(200, $limit));
        self::query()->where('state', 'processing')->where('started_at', '<', now()->subMinutes(10))->update(['state' => 'uncertain', 'outcome' => 'interrupted', 'updated_at' => now()]);
        $ids = self::query()->where('state', 'pending')->oldest('id')->limit($limit)->pluck('id');
        $processed = 0;
        foreach ($ids as $id) {
            if (self::deliverOne((int) $id)) {
                $processed++;
            }
        }

        return $processed;
    }

    public static function deliverOne(int $id): bool
    {
        if (DB::transactionLevel() > 0 && ! app()->runningUnitTests()) {
            throw new \LogicException('Email delivery must run outside application transactions.');
        }
        $preference = DB::transaction(function () use ($id): ?EmailPreference {
            $settings = app(EmailNotificationSettings::class)->refresh();
            $day = now('UTC')->format('Y-m-d');
            $scope = 'site:'.$day;
            DB::table('notification_email_budgets')->insertOrIgnore(['scope' => $scope, 'used' => 0, 'created_at' => now(), 'updated_at' => now()]);
            $site = DB::table('notification_email_budgets')->where('scope', $scope)->lockForUpdate()->first();
            $delivery = self::query()->whereKey($id)->lockForUpdate()->first();
            if (! $delivery || $delivery->state !== 'pending') {
                return null;
            }
            $source = UserNotification::query()->whereKey($delivery->notification_id)->lockForUpdate()->first();
            $user = $source ? User::query()->whereKey($source->user_id)->lockForUpdate()->first() : null;
            $preference = EmailPreference::query()->whereKey($delivery->preference_id)->lockForUpdate()->first();
            $outcome = match (true) {
                ! $settings->enabled || ! NotificationEmailSender::ready() => 'disabled',
                ! $source || $source->read_at !== null => 'source_unavailable',
                ! $preference || $preference->version !== $delivery->preference_version || ! $preference->matchesUser($user) || ! in_array($source->type, $preference->types, true) => 'consent_changed',
                $delivery->created_at->lt(now()->subDay()) => 'expired',
                default => null,
            };
            if ($outcome) {
                $delivery->update(['state' => 'skipped', 'outcome' => $outcome]);

                return null;
            }
            $scope = 'user:'.$user->id.':'.$day;
            DB::table('notification_email_budgets')->insertOrIgnore(['scope' => $scope, 'used' => 0, 'created_at' => now(), 'updated_at' => now()]);
            $budget = DB::table('notification_email_budgets')->where('scope', $scope)->lockForUpdate()->first();
            if ($site->used >= max(0, min(10000, $settings->site_daily)) || $budget->used >= max(0, min(100, $settings->per_user_daily))) {
                $delivery->update(['state' => 'skipped', 'outcome' => 'daily_limit']);

                return null;
            }
            DB::table('notification_email_budgets')->whereIn('id', [$site->id, $budget->id])->increment('used');
            $delivery->update(['state' => 'processing', 'started_at' => now()]);

            return $preference;
        }, 3);
        if (! $preference) {
            return false;
        }
        try {
            $accepted = app(NotificationEmailSender::class)->send($preference);
            self::query()->whereKey($id)->where('state', 'processing')->update(['state' => $accepted ? 'accepted' : 'failed', 'outcome' => $accepted ? 'smtp_accepted' : 'not_accepted', 'accepted_at' => $accepted ? now() : null, 'updated_at' => now()]);
        } catch (Throwable) {
            self::query()->whereKey($id)->where('state', 'processing')->update(['state' => 'uncertain', 'outcome' => 'transport_error', 'updated_at' => now()]);
        }

        return true;
    }
}
