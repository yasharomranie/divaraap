<?php

declare(strict_types=1);

namespace Modules\Admin\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Modules\Notification\Models\UserNotification;
use Modules\User\App\Models\User;
use Throwable;

final class OperationalMonitor extends Model
{
    use SoftDeletes;

    public const CHECKS = ['database', 'cache', 'scheduler', 'queue', 'failed_jobs', 'email_backlog', 'email_failures', 'server_errors', 'deployment', 'disk'];

    public const LIMITS = ['queue_minutes' => [1, 1440], 'queue_limit' => [1, 100000], 'window_minutes' => [1, 1440], 'error_limit' => [1, 100000], 'heartbeat_minutes' => [2, 60], 'disk_mb' => [1, 100000], 'deploy_minutes' => [5, 1440]];

    protected $guarded = ['*'];

    protected $hidden = ['lease_token'];

    protected function casts(): array
    {
        return ['enabled' => 'boolean', 'recipients' => 'array', 'thresholds' => 'array', 'version' => 'integer', 'sample_slot' => 'integer', 'last_checked_at' => 'datetime', 'last_scheduled_at' => 'datetime', 'lease_until' => 'datetime'];
    }

    public static function current(): self
    {
        return self::findOrFail(1);
    }

    public static function authorizeAdmin(): User
    {
        $user = User::find(auth()->id());
        abort_unless($user && (string) $user->status === 'active' && $user->hasRole('admin'), 403);

        return $user;
    }

    public static function recipientOptions(): array
    {
        return User::query()->role('admin')->where('status', 'active')->orderBy('id')->get()->mapWithKeys(fn (User $user) => [$user->id => $user->name.' #'.$user->id])->all();
    }

    public static function configure(array $data): self
    {
        $actor = self::authorizeAdmin();
        $rules = ['enabled' => 'required|boolean', 'version' => 'required|integer|min:0', 'recipients' => 'present|array|max:5', 'recipients.*' => 'integer|min:1|distinct', 'thresholds' => 'required|array:'.implode(',', array_keys(self::LIMITS))];
        foreach (self::LIMITS as $key => [$min, $max]) {
            $rules['thresholds.'.$key] = "required|integer|min:$min|max:$max";
        }
        $data = validator($data, $rules)->validate();
        $ids = array_map('intval', $data['recipients']);
        if (($data['enabled'] && ! $ids) || array_diff($ids, array_keys(self::recipientOptions()))) {
            throw ValidationException::withMessages(['data.recipients' => __('admin::monitor.invalid_recipients')]);
        }

        return DB::transaction(function () use ($data, $ids, $actor): self {
            $monitor = self::query()->whereKey(1)->lockForUpdate()->firstOrFail();
            if ($monitor->version !== (int) $data['version']) {
                throw ValidationException::withMessages(['data.version' => __('admin::monitor.stale')]);
            }
            $monitor->forceFill(['enabled' => $data['enabled'], 'recipients' => $ids, 'thresholds' => array_map('intval', $data['thresholds']), 'version' => $monitor->version + 1, 'updated_by' => $actor->id, 'last_checked_at' => null, 'sample_slot' => null, 'lease_token' => null, 'lease_until' => null])->saveOrFail();
            DB::table('operational_checks')->update(['bad_streak' => 0, 'good_streak' => 0]);

            return $monitor;
        }, 3);
    }

    public static function sample(bool $scheduled = false): string
    {
        $claim = DB::transaction(function () use ($scheduled): array|string {
            $monitor = self::query()->whereKey(1)->lockForUpdate()->firstOrFail();
            if ($scheduled) {
                $monitor->forceFill(['last_scheduled_at' => now()])->saveOrFail();
            }
            if (! $monitor->enabled) {
                return 'disabled';
            }
            if ($monitor->lease_until?->isFuture()) {
                return 'busy';
            }
            $slot = intdiv(now()->timestamp, 60);
            if ($monitor->sample_slot === $slot) {
                return 'cached';
            }
            $token = (string) Str::uuid();
            $monitor->forceFill(['lease_token' => $token, 'lease_until' => now()->addMinutes(2)])->saveOrFail();

            return [$monitor, $token, $slot];
        }, 3);
        if (is_string($claim)) {
            return $claim;
        }
        [$monitor, $token, $slot] = $claim;
        $signals = app(OperationalSignals::class)->collect($monitor);

        return DB::transaction(function () use ($signals, $monitor, $token, $slot): string {
            $current = self::query()->whereKey(1)->lockForUpdate()->firstOrFail();
            if (! $current->enabled || $current->version !== $monitor->version || $current->lease_token !== $token || ! $current->lease_until?->isFuture()) {
                return 'discarded';
            }
            foreach (self::CHECKS as $key) {
                $signal = $signals[$key];
                $old = DB::table('operational_checks')->where('check_key', $key)->first();
                $bad = ! in_array($signal['status'], ['healthy', 'not_applicable'], true);
                $badStreak = $bad ? min(100000, ($old->bad_streak ?? 0) + 1) : 0;
                $goodStreak = $bad ? 0 : min(100000, ($old->good_streak ?? 0) + 1);
                DB::table('operational_checks')->updateOrInsert(['check_key' => $key], $signal + ['bad_streak' => $badStreak, 'good_streak' => $goodStreak, 'sampled_at' => now(), 'created_at' => $old->created_at ?? now(), 'updated_at' => now()]);
                $incident = DB::table('operational_incidents')->where('active_key', $key)->first();
                if ($bad && $incident) {
                    DB::table('operational_incidents')->where('id', $incident->id)->update(['last_seen_at' => now(), 'updated_at' => now()]);
                } elseif ($badStreak >= 2 && ! $incident) {
                    DB::table('operational_incidents')->insert(['check_key' => $key, 'active_key' => $key, 'opened_at' => now(), 'last_seen_at' => now(), 'created_at' => now(), 'updated_at' => now()]);
                    self::notify($current, $key, false);
                } elseif ($goodStreak >= 2 && $incident) {
                    DB::table('operational_incidents')->where('id', $incident->id)->update(['active_key' => null, 'resolved_at' => now(), 'version' => $incident->version + 1, 'updated_at' => now()]);
                    self::notify($current, $key, true);
                }
            }
            $current->forceFill(['last_checked_at' => now(), 'sample_slot' => $slot, 'lease_token' => null, 'lease_until' => null])->saveOrFail();

            return 'sampled';
        }, 3);
    }

    private static function notify(self $monitor, string $key, bool $recovered): void
    {
        foreach (User::query()->whereIn('id', $monitor->recipients)->role('admin')->where('status', 'active')->get() as $user) {
            UserNotification::publish((int) $user->id, 'operations', __('admin::monitor.'.($recovered ? 'recovered' : 'alert')), __('admin::monitor.check_'.$key), '/admin/operational-health');
        }
    }

    public static function snapshot(): array
    {
        return DB::table('operational_checks')->whereNull('deleted_at')->get()->keyBy('check_key')->all();
    }

    public function freshSample(): bool
    {
        return $this->enabled && $this->last_checked_at && $this->last_checked_at->gte(now()->subMinutes($this->thresholds['heartbeat_minutes']));
    }

    public static function probeHealthy(): bool
    {
        try {
            DB::connection()->select('SELECT 1');
            $monitor = self::current();
            if (! $monitor->freshSample() || ! $monitor->last_scheduled_at || $monitor->last_scheduled_at->lt(now()->subMinutes($monitor->thresholds['heartbeat_minutes']))) {
                return false;
            }
            $checks = self::snapshot();
            foreach (self::CHECKS as $key) {
                if (! isset($checks[$key]) || ! in_array($checks[$key]->status, ['healthy', 'not_applicable'], true)) {
                    return false;
                }
            }

            return true;
        } catch (Throwable) {
            return false;
        }
    }
}
