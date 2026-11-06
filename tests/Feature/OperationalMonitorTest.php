<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Support\Deployment\DeploymentManager;
use Filament\Facades\Filament;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Modules\Admin\Filament\Pages\ManageOperationalMonitor;
use Modules\Admin\Filament\Pages\OperationalHealth;
use Modules\Admin\Models\OperationalIncident;
use Modules\Admin\Models\OperationalMonitor;
use Modules\Admin\Models\OperationalSignals;
use Modules\Notification\Models\EmailPreference;
use Modules\Notification\Models\UserNotification;
use Modules\Site\App\Settings\EmailNotificationSettings;
use Modules\User\App\Models\User;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Tests\TestCase;

final class OperationalMonitorTest extends TestCase
{
    private User $admin;

    private User $staff;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create(['status' => 'active']);
        $this->admin->assignRole('admin');
        $this->staff = User::factory()->create(['status' => 'active']);
        $this->staff->assignRole('support');
        $this->actingAs($this->admin);
        Filament::setCurrentPanel(Filament::getPanel('admin'));
    }

    public function test_default_is_off_and_only_full_active_admins_see_infrastructure(): void
    {
        $this->assertFalse(OperationalMonitor::current()->enabled);
        $this->assertSame('disabled', OperationalMonitor::sample());
        $this->get('/admin/operational-health')->assertOk()->assertSee(__('admin::monitor.disabled'));
        $this->get('/admin/manage-operational-monitor')->assertOk();
        $this->actingAs($this->staff);
        $this->get('/admin/operational-health')->assertForbidden();
        $this->get('/admin/manage-operational-monitor')->assertForbidden();
        $this->expectException(HttpException::class);
        OperationalMonitor::configure($this->data());
    }

    public function test_config_validates_recipients_thresholds_and_optimistic_version(): void
    {
        foreach ([['recipients' => []], ['recipients' => [$this->staff->id]], ['thresholds' => ['disk_mb' => 0]], ['recipients' => array_fill(0, 6, $this->admin->id)]] as $patch) {
            try {
                OperationalMonitor::configure(array_replace($this->data(), $patch));
                $this->fail('Invalid configuration accepted');
            } catch (ValidationException) {
                $this->assertFalse(OperationalMonitor::current()->enabled);
            }
        }
        $this->enable();
        $this->assertTrue(OperationalMonitor::current()->enabled);
        $this->expectException(ValidationException::class);
        OperationalMonitor::configure($this->data());
    }

    public function test_real_livewire_settings_save_and_scan(): void
    {
        Livewire::test(ManageOperationalMonitor::class)->fillForm($this->data())->call('save')->assertHasNoFormErrors();
        $this->assertTrue(OperationalMonitor::current()->enabled);
        Livewire::test(OperationalHealth::class)->call('sample')->assertSee(__('admin::monitor.code_heartbeat'));
        $this->assertCount(10, OperationalMonitor::snapshot());
    }

    public function test_manual_checks_do_not_fake_scheduler_heartbeat(): void
    {
        $this->enable();
        $this->assertSame('sampled', OperationalMonitor::sample());
        $this->assertNull(OperationalMonitor::current()->last_scheduled_at);
        $this->assertSame('critical', OperationalMonitor::snapshot()['scheduler']->status);
        $this->assertSame('cached', OperationalMonitor::sample(true));
        $this->assertNotNull(OperationalMonitor::current()->last_scheduled_at);
        $this->assertSame('critical', OperationalMonitor::snapshot()['scheduler']->status);
        $this->travel(1)->minutes();
        OperationalMonitor::sample(true);
        $this->assertSame('healthy', OperationalMonitor::snapshot()['scheduler']->status);
    }

    public function test_disabled_scheduled_command_records_invocation_without_samples(): void
    {
        $this->artisan('operations:monitor --scheduled')->expectsOutput('disabled')->assertSuccessful();
        $this->assertNotNull(OperationalMonitor::current()->last_scheduled_at);
        $this->assertSame([], OperationalMonitor::snapshot());
    }

    public function test_two_bad_samples_open_once_same_minute_cannot_inflate_streaks(): void
    {
        $this->enable();
        $this->fakeSignals('critical');
        OperationalMonitor::sample(true);
        $this->assertSame(0, OperationalIncident::count());
        $this->assertSame('cached', OperationalMonitor::sample(true));
        $this->assertSame(1, OperationalMonitor::snapshot()['database']->bad_streak);
        $this->travel(1)->minutes();
        OperationalMonitor::sample(true);
        $this->assertSame(1, OperationalIncident::count());
        $this->assertSame(1, $this->alerts());
        $this->travel(1)->minutes();
        OperationalMonitor::sample(true);
        $this->assertSame(1, $this->alerts());
        $this->assertSame(1, OperationalIncident::count());
        $this->assertSame(0, DB::table('notification_email_deliveries')->whereIn('notification_id', UserNotification::where('type', 'operations')->pluck('id'))->count());
    }

    public function test_acknowledgement_is_private_idempotent_and_does_not_resolve_or_green_probe(): void
    {
        $this->incident();
        $incident = OperationalIncident::sole();
        OperationalIncident::acknowledge($incident->id, 0, 'Private operator note');
        OperationalIncident::acknowledge($incident->id, 0, 'Private operator note');
        $fresh = $incident->fresh();
        $this->assertSame(1, $fresh->version);
        $this->assertNull($fresh->resolved_at);
        $this->assertSame($this->admin->id, $fresh->acknowledged_by);
        $this->assertStringNotContainsString('Private operator note', DB::table('operational_incidents')->value('note'));
        $this->assertArrayNotHasKey('note', $fresh->toArray());
        $this->assertFalse(OperationalMonitor::probeHealthy());
        $this->expectException(ValidationException::class);
        OperationalIncident::acknowledge($incident->id, 0, 'Different note');
    }

    public function test_actual_acknowledgement_dialog_records_review(): void
    {
        $this->incident();
        $incident = OperationalIncident::sole();
        Livewire::test(OperationalHealth::class)
            ->callAction('acknowledge', ['note' => 'Investigating'], arguments: ['id' => $incident->id, 'version' => 0])
            ->assertHasNoActionErrors();
        $this->assertNotNull($incident->fresh()->acknowledged_at);
    }

    public function test_two_good_samples_resolve_and_later_outage_creates_new_history(): void
    {
        $this->incident();
        $this->fakeSignals('healthy');
        $this->travel(1)->minutes();
        OperationalMonitor::sample(true);
        $this->assertNull(OperationalIncident::sole()->resolved_at);
        $this->travel(1)->minutes();
        OperationalMonitor::sample(true);
        $this->assertNotNull(OperationalIncident::sole()->resolved_at);
        $this->assertSame(2, $this->alerts());
        $this->fakeSignals('critical');
        $this->travel(1)->minutes();
        OperationalMonitor::sample(true);
        $this->travel(1)->minutes();
        OperationalMonitor::sample(true);
        $this->assertSame(2, OperationalIncident::count());
        $this->assertSame(1, OperationalIncident::whereNotNull('active_key')->count());
    }

    public function test_claim_lease_and_configuration_change_discard_old_measurement(): void
    {
        $this->enable();
        DB::table('operational_monitors')->where('id', 1)->update(['lease_token' => Str::uuid(), 'lease_until' => now()->addMinute()]);
        $this->assertSame('busy', OperationalMonitor::sample());
        $this->travel(3)->minutes();
        $this->app->bind(OperationalSignals::class, fn () => new class extends OperationalSignals
        {
            public function collect(OperationalMonitor $monitor): array
            {
                DB::table('operational_monitors')->where('id', 1)->update(['enabled' => false, 'version' => $monitor->version + 1, 'lease_token' => null]);

                return array_fill_keys(OperationalMonitor::CHECKS, self::signal('healthy', 'reachable'));
            }
        });
        $this->assertSame('discarded', OperationalMonitor::sample());
        $this->assertSame([], OperationalMonitor::snapshot());
    }

    public function test_real_queue_inspection_excludes_future_jobs_and_payloads(): void
    {
        $this->enable();
        config()->set('queue.default', 'database');
        DB::table('jobs')->insert(['queue' => 'default', 'payload' => 'PRIVATE_JOB_SECRET', 'attempts' => 0, 'available_at' => now()->addHour()->timestamp, 'created_at' => now()->timestamp]);
        $this->assertSame(0, app(OperationalSignals::class)->collect(OperationalMonitor::current())['queue']['value']);
        DB::table('jobs')->insert(['queue' => 'default', 'payload' => 'PRIVATE_JOB_SECRET', 'attempts' => 0, 'available_at' => now()->subHour()->timestamp, 'created_at' => now()->subHour()->timestamp]);
        $signals = app(OperationalSignals::class)->collect(OperationalMonitor::current());
        $this->assertSame('warning', $signals['queue']['status']);
        $this->assertSame(1, $signals['queue']['value']);
        $this->assertStringNotContainsString('PRIVATE_JOB_SECRET', json_encode($signals));
    }

    public function test_stale_queue_reservation_and_recent_failed_jobs_are_detected(): void
    {
        $this->enable();
        config()->set('queue.default', 'database');
        DB::table('jobs')->insert(['queue' => 'default', 'payload' => 'secret', 'attempts' => 1, 'reserved_at' => now()->subHours(2)->timestamp, 'available_at' => now()->subHours(2)->timestamp, 'created_at' => now()->timestamp]);
        DB::connection(config('queue.failed.database'))->table(config('queue.failed.table'))->insert(['uuid' => Str::uuid(), 'connection' => 'database', 'queue' => 'default', 'payload' => 'secret', 'exception' => 'PRIVATE_EXCEPTION', 'failed_at' => now()]);
        $signals = app(OperationalSignals::class)->collect(OperationalMonitor::current());
        $this->assertSame('stale_reservation', $signals['queue']['code']);
        $this->assertSame('warning', $signals['failed_jobs']['status']);
        $this->assertStringNotContainsString('PRIVATE_EXCEPTION', json_encode($signals));
    }

    public function test_unsupported_driver_never_reports_false_health(): void
    {
        config()->set('queue.default', 'redis');
        $signals = app(OperationalSignals::class)->collect(OperationalMonitor::current());
        $this->assertSame('unknown', $signals['queue']['status']);
        $this->assertSame('not_applicable', $signals['email_backlog']['status']);
        config()->set('queue.connections.database.table', 'jobs; SELECT secret');
        config()->set('queue.default', 'database');
        $this->assertSame('unknown', app(OperationalSignals::class)->collect(OperationalMonitor::current())['queue']['status']);
    }

    public function test_exception_counter_excludes_expected_errors_and_never_stores_messages(): void
    {
        OperationalSignals::recordServerError(new \RuntimeException('DISABLED_SECRET'));
        $this->assertSame(0, DB::table('operational_error_buckets')->count());
        $this->enable();
        OperationalSignals::recordServerError(new NotFoundHttpException('EXPECTED_PRIVATE'));
        OperationalSignals::recordServerError(ValidationException::withMessages(['field' => 'PRIVATE_INPUT']));
        $this->assertSame(0, DB::table('operational_error_buckets')->count());
        for ($i = 0; $i < 5; $i++) {
            OperationalSignals::recordServerError(new \RuntimeException('PRIVATE_ERROR_TOKEN'));
        }
        $this->assertSame(5, (int) DB::table('operational_error_buckets')->sum('count'));
        $signals = app(OperationalSignals::class)->collect(OperationalMonitor::current());
        $this->assertSame('critical', $signals['server_errors']['status']);
        $this->assertStringNotContainsString('PRIVATE_ERROR_TOKEN', json_encode(DB::table('operational_error_buckets')->get()));
    }

    public function test_probe_is_private_minimal_and_fails_closed_for_stale_unknown_or_disabled(): void
    {
        $token = str_repeat('a', 64);
        $this->getJson('/operations/health')->assertNotFound();
        config()->set('operational-monitor.probe_token', $token);
        $this->withToken('wrong')->getJson('/operations/health')->assertNotFound();
        $this->withToken($token)->getJson('/operations/health')->assertStatus(503)->assertExactJson(['ok' => false]);
        $this->enable();
        $this->fakeSignals('healthy');
        OperationalMonitor::sample(true);
        $this->withToken($token)->getJson('/operations/health')->assertOk()->assertExactJson(['ok' => true])->assertHeader('Cache-Control', 'no-store, private');
        $this->travel(6)->minutes();
        $this->withToken($token)->getJson('/operations/health')->assertStatus(503);
        $this->assertSame(0, OperationalIncident::count());
    }

    public function test_recipient_permission_is_revalidated_when_alert_opens(): void
    {
        $this->enable();
        $this->fakeSignals('critical');
        OperationalMonitor::sample(true);
        $this->admin->removeRole('admin');
        $this->travel(1)->minutes();
        OperationalMonitor::sample(true);
        $this->assertSame(1, OperationalIncident::count());
        $this->assertSame(0, $this->alerts());
    }

    public function test_local_deployment_status_is_limited_and_detects_failure(): void
    {
        $path = storage_path('framework/testing/monitor-'.Str::uuid());
        mkdir($path, 0775, true);
        file_put_contents($path.'/installed.lock', '1');
        file_put_contents($path.'/deployment-job.json', json_encode(['state' => 'failed', 'token_hash' => 'PRIVATE_DEPLOY_TOKEN', 'error' => 'PRIVATE_PATH']));
        config()->set('deployment.root', $path);
        try {
            $signal = app(DeploymentManager::class)->monitoringSnapshot(30);
            $this->assertSame('critical', $signal['status']);
            $this->assertStringNotContainsString('PRIVATE', json_encode($signal));
            file_put_contents($path.'/deployment-job.json', json_encode(['state' => 'completed']));
            file_put_contents($path.'/deployment-maintenance.json', '{}');
            $this->assertSame('warning', app(DeploymentManager::class)->monitoringSnapshot(30)['status']);
            unlink($path.'/deployment-maintenance.json');
            $this->assertSame('healthy', app(DeploymentManager::class)->monitoringSnapshot(30)['status']);
        } finally {
            unlink($path.'/installed.lock');
            unlink($path.'/deployment-job.json');
            rmdir($path);
        }
    }

    public function test_email_monitor_counts_backlog_and_uncertain_without_attempting_delivery(): void
    {
        $settings = app(EmailNotificationSettings::class);
        $settings->enabled = true;
        $settings->save();
        config()->set('mail.default', 'smtp');
        config()->set('mail.mailers.smtp', ['transport' => 'smtp', 'host' => 'localhost', 'port' => 1]);
        config()->set('mail.from.address', 'monitor@example.test');
        $preference = EmailPreference::query()->create(['user_id' => $this->admin->id, 'enabled' => false, 'types' => []]);
        $source = UserNotification::publish($this->admin->id, 'operations', 'No email');
        foreach (['pending', 'processing', 'uncertain'] as $state) {
            if ($state !== 'pending') {
                $source = UserNotification::publish($this->admin->id, 'operations', 'No email');
            }
            DB::table('notification_email_deliveries')->insert(['notification_id' => $source->id, 'preference_id' => $preference->id, 'preference_version' => 0, 'state' => $state, 'started_at' => now()->subMinutes(20), 'created_at' => now()->subMinutes(20), 'updated_at' => now()]);
        }
        $signals = app(OperationalSignals::class)->collect(OperationalMonitor::current());
        $this->assertSame('warning', $signals['email_backlog']['status']);
        $this->assertSame(2, $signals['email_backlog']['value']);
        $this->assertSame(1, $signals['email_failures']['value']);
        $this->assertSame(3, DB::table('notification_email_deliveries')->count());
        $this->assertSame(0, DB::table('notification_email_deliveries')->where('state', 'accepted')->count());
    }

    public function test_probe_database_failure_returns_only_unavailable_and_reports_do_not_recurse(): void
    {
        config()->set('operational-monitor.probe_token', str_repeat('b', 64));
        $original = config('database.default');
        config()->set('database.default', 'monitor_unavailable');
        config()->set('database.connections.monitor_unavailable', ['driver' => 'invalid_driver']);
        $this->withToken(str_repeat('b', 64))->getJson('/operations/health')->assertStatus(503)->assertExactJson(['ok' => false]);
        OperationalSignals::recordServerError(new \RuntimeException('PRIVATE_DATABASE_ERROR'));
        config()->set('database.default', $original);
    }

    public function test_laravel_reporting_hook_counts_only_metadata_and_preserves_normal_reporting(): void
    {
        $this->enable();
        $logger = \Mockery::mock(LoggerInterface::class);
        $logger->shouldReceive('error')->once();
        $this->app->instance(LoggerInterface::class, $logger);
        $handler = app(ExceptionHandler::class);
        $handler->report(new \RuntimeException('PRIVATE_REPORT_CONTENT'));
        $this->assertSame(1, (int) DB::table('operational_error_buckets')->sum('count'));
        $this->assertStringNotContainsString('PRIVATE_REPORT_CONTENT', json_encode(DB::table('operational_error_buckets')->get()));
    }

    private function data(): array
    {
        return ['enabled' => true, 'recipients' => [$this->admin->id], 'version' => 0, 'thresholds' => OperationalMonitor::current()->thresholds];
    }

    private function enable(): void
    {
        OperationalMonitor::configure($this->data());
    }

    private function fakeSignals(string $database): void
    {
        $this->app->bind(OperationalSignals::class, fn () => new class($database) extends OperationalSignals
        {
            public function __construct(private string $status) {}

            public function collect(OperationalMonitor $monitor): array
            {
                $signals = array_fill_keys(OperationalMonitor::CHECKS, self::signal('healthy', 'reachable'));
                $signals['database'] = self::signal($this->status, 'reachable');

                return $signals;
            }
        });
    }

    private function incident(): void
    {
        $this->enable();
        $this->fakeSignals('critical');
        OperationalMonitor::sample(true);
        $this->travel(1)->minutes();
        OperationalMonitor::sample(true);
    }

    private function alerts(): int
    {
        return UserNotification::where('type', 'operations')->where('user_id', $this->admin->id)->count();
    }
}
