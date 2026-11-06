<?php

declare(strict_types=1);

namespace Modules\Admin\Models;

use App\Support\Deployment\DeploymentManager;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Modules\Notification\Models\EmailDelivery;
use Modules\Notification\Services\NotificationEmailSender;
use Modules\Site\App\Settings\EmailNotificationSettings;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;

class OperationalSignals extends Model
{
    private static bool $recording = false;

    public function collect(OperationalMonitor $monitor): array
    {
        $signals = [];
        foreach (OperationalMonitor::CHECKS as $key) {
            try {
                $signals[$key] = $this->check($key, $monitor);
            } catch (Throwable) {
                $signals[$key] = self::signal('unknown', 'unavailable');
            }
        }

        return $signals;
    }

    public static function signal(string $status, string $code, ?int $value = null): array
    {
        return ['status' => $status, 'code' => $code, 'value' => $value === null ? null : max(0, $value)];
    }

    private function check(string $key, OperationalMonitor $monitor): array
    {
        $t = $monitor->thresholds;
        if ($key === 'database') {
            DB::connection()->select('SELECT 1');

            return self::signal('healthy', 'reachable');
        }
        if ($key === 'cache') {
            $key = 'operations-probe:'.Str::uuid();
            $value = Str::random(24);
            try {
                Cache::put($key, $value, 30);

                return self::signal(Cache::get($key) === $value ? 'healthy' : 'critical', 'roundtrip');
            } finally {
                Cache::forget($key);
            }
        }
        if ($key === 'scheduler') {
            $age = $monitor->last_scheduled_at ? (int) $monitor->last_scheduled_at->diffInSeconds(now(), true) : null;

            return self::signal($age !== null && $age <= $t['heartbeat_minutes'] * 60 ? 'healthy' : 'critical', 'heartbeat', $age);
        }
        if ($key === 'queue') {
            $queue = config('queue.connections.'.config('queue.default'), []);
            if (in_array($queue['driver'] ?? '', ['sync', 'deferred', 'background'], true)) {
                return self::signal('not_applicable', 'no_persistent_queue');
            }
            if (($queue['driver'] ?? '') !== 'database') {
                return self::signal('unknown', 'unsupported_driver');
            }
            $table = $this->table($queue['table'] ?? 'jobs');
            $query = DB::connection($queue['connection'] ?? null)->table($table)->where('queue', $queue['queue'] ?? 'default');
            $ready = (clone $query)->whereNull('reserved_at')->where('available_at', '<=', now()->timestamp);
            $count = $ready->count();
            $oldest = $ready->min('available_at');
            $stuck = (clone $query)->whereNotNull('reserved_at')->where('reserved_at', '<', now()->timestamp - (int) ($queue['retry_after'] ?? 90) - 60)->count();
            $bad = $count >= $t['queue_limit'] || ($oldest !== null && now()->timestamp - (int) $oldest > $t['queue_minutes'] * 60) || $stuck > 0;

            return self::signal($bad ? 'warning' : 'healthy', $stuck > 0 ? 'stale_reservation' : 'pending_jobs', $count + $stuck);
        }
        if ($key === 'failed_jobs') {
            $failed = config('queue.failed', []);
            if (! in_array($failed['driver'] ?? '', ['database', 'database-uuids'], true)) {
                return self::signal('unknown', 'unsupported_driver');
            }
            $count = DB::connection($failed['database'] ?? null)->table($this->table($failed['table'] ?? 'failed_jobs'))->where('failed_at', '>=', now()->subMinutes($t['window_minutes']))->count();

            return self::signal($count > 0 ? 'warning' : 'healthy', 'recent_failures', $count);
        }
        if (in_array($key, ['email_backlog', 'email_failures'], true)) {
            $email = EmailDelivery::monitoringSummary($t['queue_minutes'], $t['window_minutes']);
            if ($key === 'email_backlog') {
                if (! app(EmailNotificationSettings::class)->enabled) {
                    return self::signal('not_applicable', 'email_disabled');
                }
                if (! NotificationEmailSender::ready()) {
                    return self::signal('critical', 'email_configuration');
                }

                return self::signal($email['backlog'] > 0 ? 'warning' : 'healthy', 'email_waiting', $email['backlog']);
            }

            return self::signal($email['failures'] > 0 ? 'warning' : 'healthy', 'email_uncertain', $email['failures']);
        }
        if ($key === 'server_errors') {
            $count = (int) DB::table('operational_error_buckets')->whereNull('deleted_at')->where('bucket_at', '>=', now()->subMinutes($t['window_minutes'])->startOfMinute())->sum('count');

            return self::signal($count >= $t['error_limit'] ? 'critical' : 'healthy', 'reported_errors', $count);
        }
        if ($key === 'deployment') {
            return app(DeploymentManager::class)->monitoringSnapshot($t['deploy_minutes']);
        }
        $bytes = @disk_free_space(storage_path());
        if ($bytes === false) {
            return self::signal('unknown', 'unavailable');
        }
        $mb = (int) floor($bytes / 1048576);

        return self::signal($mb < $t['disk_mb'] ? 'critical' : 'healthy', 'free_mb', $mb);
    }

    private function table(string $table): string
    {
        if (! preg_match('/\A[a-zA-Z_][a-zA-Z0-9_]*\z/', $table)) {
            throw new \RuntimeException('Invalid table configuration.');
        }

        return $table;
    }

    public static function recordServerError(Throwable $error): void
    {
        if (self::$recording || $error instanceof HttpExceptionInterface && $error->getStatusCode() < 500 || $error instanceof ValidationException || $error instanceof AuthenticationException || $error instanceof AuthorizationException || $error instanceof ModelNotFoundException) {
            return;
        }
        self::$recording = true;
        try {
            if (OperationalMonitor::current()->enabled) {
                $bucket = now()->utc()->startOfMinute();
                DB::table('operational_error_buckets')->insertOrIgnore(['bucket_at' => $bucket, 'count' => 0, 'created_at' => now(), 'updated_at' => now()]);
                DB::table('operational_error_buckets')->where('bucket_at', $bucket)->where('count', '<', 2147483647)->increment('count');
            }
        } catch (Throwable) {
        } finally {
            self::$recording = false;
        }
    }
}
