<?php

declare(strict_types=1);

namespace Tests\Feature;

use Filament\Facades\Filament;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Mockery;
use Modules\Advertising\Models\AdvertisingInvoice;
use Modules\Advertising\Models\AdvertisingPayment;
use Modules\Advertising\Models\AdvertisingRefundRequest;
use Modules\Notification\Filament\Admin\Resources\EmailDeliveryResource\Pages\ListEmailDeliveries;
use Modules\Notification\Mail\ActivityNotice;
use Modules\Notification\Models\EmailDelivery;
use Modules\Notification\Models\EmailPreference;
use Modules\Notification\Models\UserNotification;
use Modules\Notification\Services\NotificationEmailSender;
use Modules\Site\App\Settings\EmailNotificationSettings;
use Modules\Site\Filament\Admin\Pages\ManageEmailNotifications;
use Modules\User\App\Models\User;
use Tests\TestCase;

final class EmailNotificationDeliveryTest extends TestCase
{
    private User $user;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create(['status' => 'active', 'email_verified_at' => now()]);
        $this->admin = User::where('email', 'a@a.com')->firstOrFail();
        $this->actingAs($this->user);
        config(['mail.default' => 'smtp', 'app.url' => 'https://example.test', 'mail.from.address' => 'notice@example.test']);
        $this->settings(true);
        $this->instance(NotificationEmailSender::class, Mockery::mock(NotificationEmailSender::class)->shouldReceive('send')->andReturn(true)->getMock());
        Filament::setCurrentPanel(Filament::getPanel('admin'));
    }

    public function test_defaults_and_missing_consent_never_backfill_or_enqueue(): void
    {
        $this->settings(false);
        $source = $this->notice();
        $this->preference();
        $this->notice();
        $this->assertSame(0, EmailDelivery::count());
        $this->settings(true);
        $this->assertSame(0, EmailDelivery::deliverBatch());
        $this->assertSame(0, EmailDelivery::count());
        $this->assertNotNull($source->fresh());
    }

    public function test_verified_email_explicit_consent_and_selected_types_are_required(): void
    {
        $this->invalid(fn () => EmailPreference::updateFor($this->user, true, ['message'], 0, false, 'fa'), 'consent');
        $this->invalid(fn () => EmailPreference::updateFor($this->user, true, [], 0, true, 'fa'), 'consent');
        $this->invalid(fn () => EmailPreference::updateFor($this->user, true, ['unknown'], 0, true, 'fa'), 'consent');
        $this->user->forceFill(['email_verified_at' => null])->save();
        $this->invalid(fn () => $this->preference(), 'consent');
        $this->assertSame(0, EmailPreference::count());
    }

    public function test_preferences_are_private_encrypted_versioned_and_email_bound(): void
    {
        $p = $this->preference();
        $this->assertTrue($p->matchesUser($this->user));
        $this->assertSame(1, $p->version);
        $this->assertSame('fa', $p->locale);
        $this->assertNotSame($this->user->email, DB::table('notification_email_preferences')->where('id', $p->id)->value('recipient'));
        $this->assertNotSame($p->unsubscribe_token, DB::table('notification_email_preferences')->where('id', $p->id)->value('unsubscribe_token'));
        $this->assertArrayNotHasKey('recipient', $p->toArray());
        $this->assertArrayNotHasKey('unsubscribe_token', $p->toArray());
        $this->invalid(fn () => EmailPreference::updateFor($this->user, false, [], 0, false, 'fa'), 'version');
        $this->user->forceFill(['email' => 'new-address@example.test'])->save();
        $this->assertFalse($p->matchesUser($this->user->fresh()));
    }

    public function test_only_new_unread_selected_types_enqueue_once(): void
    {
        $this->preference();
        $this->notice('offer');
        $source = $this->notice();
        EmailDelivery::enqueue($source);
        $this->notice('unknown');
        UserNotification::create(['user_id' => $this->user->id, 'type' => 'message', 'title' => 'Already read', 'read_at' => now()]);
        $this->assertSame(1, EmailDelivery::count());
        $this->assertSame($source->id, EmailDelivery::sole()->notification_id);
    }

    public function test_accepted_send_is_not_repeated_and_has_no_private_payload_copy(): void
    {
        $this->preference();
        $source = $this->notice();
        $delivery = EmailDelivery::sole();
        $sender = Mockery::mock(NotificationEmailSender::class);
        $sender->shouldReceive('send')->once()->withArgs(fn (EmailPreference $p) => $p->user_id === $this->user->id)->andReturn(true);
        $this->instance(NotificationEmailSender::class, $sender);
        $this->assertTrue(EmailDelivery::deliverOne($delivery->id));
        $this->assertFalse(EmailDelivery::deliverOne($delivery->id));
        $this->assertSame(0, EmailDelivery::deliverBatch());
        $this->assertSame('accepted', $delivery->fresh()->state);
        $this->assertSame('smtp_accepted', $delivery->fresh()->outcome);
        $this->assertNotNull($delivery->fresh()->accepted_at);
        $this->assertStringNotContainsString('PRIVATE BODY', $delivery->fresh()->toJson());
        $this->assertNull($source->fresh()->read_at);
    }

    public function test_unsubscribe_before_processing_invalidates_pending_work_without_login(): void
    {
        $p = $this->preference();
        $this->notice();
        $url = '/notification-email/unsubscribe/'.$p->unsubscribe_token;
        auth()->logout();
        $this->get($url)->assertOk()->assertHeader('Referrer-Policy', 'no-referrer')->assertDontSee($this->user->email);
        $this->assertTrue($p->fresh()->enabled);
        $this->post($url)->assertOk()->assertSee(__('notification::email.unsubscribed'));
        $this->assertFalse($p->fresh()->enabled);
        $this->assertSame(0, EmailDelivery::deliverBatch());
        $this->assertSame('consent_changed', EmailDelivery::sole()->outcome);
        $this->post($url)->assertOk();
    }

    public function test_old_unsubscribe_link_cannot_cancel_fresh_consent_and_unknown_links_are_generic(): void
    {
        $p = $this->preference();
        $old = $p->unsubscribe_token;
        EmailPreference::unsubscribe($old);
        $current = EmailPreference::updateFor($this->user, true, ['message'], 2, true, 'en');
        EmailPreference::unsubscribe($old);
        $this->assertTrue($current->fresh()->enabled);
        $this->assertNotSame($old, $current->unsubscribe_token);
        $this->get('/notification-email/unsubscribe/'.str_repeat('a', 64))->assertOk()->assertDontSee($this->user->email);
        $this->post('/notification-email/unsubscribe/'.str_repeat('a', 64))->assertOk()->assertSee(__('notification::email.unsubscribed'));
        $this->get('/notification-email/unsubscribe/bad')->assertNotFound();
    }

    public function test_read_or_deleted_notifications_and_changed_or_disabled_accounts_skip(): void
    {
        $this->preference();
        $source = $this->notice();
        $source->markRead();
        EmailDelivery::deliverBatch();
        $this->assertSame('source_unavailable', EmailDelivery::sole()->outcome);
        $second = $this->notice();
        $second->delete();
        EmailDelivery::deliverBatch();
        $this->assertSame('source_unavailable', EmailDelivery::latest('id')->first()->outcome);
        $third = $this->notice();
        $this->user->forceFill(['email' => 'changed@example.test'])->save();
        EmailDelivery::deliverBatch();
        $this->assertSame('consent_changed', EmailDelivery::where('notification_id', $third->id)->sole()->outcome);
    }

    public function test_deleted_and_inactive_users_do_not_receive_queued_email(): void
    {
        $this->preference();
        $this->notice();
        $this->user->forceFill(['status' => 'suspended'])->save();
        $this->assertSame(0, EmailDelivery::deliverBatch());
        $this->assertSame('consent_changed', EmailDelivery::sole()->outcome);
        $this->user->forceFill(['status' => 'active'])->save();
        $this->notice();
        $this->user->delete();
        $this->assertSame(0, EmailDelivery::deliverBatch());
        $this->assertSame('consent_changed', EmailDelivery::latest('id')->first()->outcome);
    }

    public function test_global_stop_and_expiration_do_not_replay_later(): void
    {
        $this->preference();
        $this->notice();
        $this->settings(false);
        $this->assertSame(0, EmailDelivery::deliverBatch());
        $this->assertSame('disabled', EmailDelivery::sole()->outcome);
        $this->settings(true);
        $this->notice();
        EmailDelivery::latest('id')->first()->update(['created_at' => now()->subHours(25)]);
        $this->assertSame(0, EmailDelivery::deliverBatch());
        $this->assertSame('expired', EmailDelivery::latest('id')->first()->outcome);
    }

    public function test_ambiguous_transport_failures_are_not_retried_or_logged_with_sensitive_errors(): void
    {
        $this->preference();
        $this->notice();
        $sender = Mockery::mock(NotificationEmailSender::class);
        $sender->shouldReceive('send')->once()->andThrow(new \RuntimeException('SECRET SMTP PASSWORD'));
        $this->instance(NotificationEmailSender::class, $sender);
        $this->assertSame(1, EmailDelivery::deliverBatch());
        $this->assertSame('uncertain', EmailDelivery::sole()->state);
        $this->assertSame('transport_error', EmailDelivery::sole()->outcome);
        $this->assertSame(0, EmailDelivery::deliverBatch());
        $this->assertStringNotContainsString('SECRET', EmailDelivery::sole()->toJson());
        $this->assertSame(2, (int) DB::table('notification_email_budgets')->sum('used'));
    }

    public function test_interrupted_processing_is_marked_uncertain_and_never_resent(): void
    {
        $this->preference();
        $this->notice();
        EmailDelivery::sole()->update(['state' => 'processing', 'started_at' => now()->subMinutes(11)]);
        $this->assertSame(0, EmailDelivery::deliverBatch());
        $this->assertSame('uncertain', EmailDelivery::sole()->state);
        $this->assertSame('interrupted', EmailDelivery::sole()->outcome);
    }

    public function test_user_and_site_attempt_budgets_bound_delivery_even_when_limits_change(): void
    {
        $this->preference();
        $this->settings(true, 1, 2);
        $this->notice();
        $this->notice();
        $this->assertSame(1, EmailDelivery::deliverBatch());
        $this->assertSame(1, EmailDelivery::where('state', 'accepted')->count());
        $this->assertSame('daily_limit', EmailDelivery::latest('id')->first()->outcome);
        $other = User::factory()->create(['status' => 'active', 'email_verified_at' => now()]);
        $this->actingAs($other);
        EmailPreference::updateFor($other, true, ['message'], 0, true, 'en');
        UserNotification::publish($other->id, 'message', 'Other');
        $this->assertSame(1, EmailDelivery::deliverBatch());
        $this->settings(true, 3, 2);
        UserNotification::publish($other->id, 'message', 'Site cap');
        $this->assertSame(0, EmailDelivery::deliverBatch());
        $this->assertSame(2, EmailDelivery::where('state', 'accepted')->count());
    }

    public function test_native_preferences_ignore_spoofed_recipient_and_preserve_invalid_input(): void
    {
        $other = User::factory()->create(['status' => 'active']);
        $this->get('/panel/notification-email')->assertOk()->assertHeader('Cache-Control', 'no-store, private');
        $this->post('/panel/notification-email', ['enabled' => 1, 'version' => 0, 'types' => ['message'], 'consent' => 1, 'user_id' => $other->id, 'recipient' => 'attacker@example.test'])->assertRedirect('/panel/notification-email');
        $p = EmailPreference::sole();
        $this->assertSame($this->user->id, $p->user_id);
        $this->assertSame($this->user->email, $p->recipient);
        $this->from('/panel/notification-email')->post('/panel/notification-email', ['enabled' => 1, 'version' => 1, 'types' => 'bad'])->assertSessionHasErrors('types');
        $this->get('/panel/notification-email')->assertOk();
        $this->post('/panel/notification-email', ['enabled' => 0, 'version' => 1])->assertRedirect();
        $this->assertFalse($p->fresh()->enabled);
    }

    public function test_preferences_and_admin_tracking_enforce_account_and_full_admin_access(): void
    {
        auth()->logout();
        $this->get('/panel/notification-email')->assertRedirect('/login');
        $this->actingAs($this->user);
        $this->get('/admin/manage-email-notifications')->assertForbidden();
        $this->get('/admin/email-deliveries')->assertForbidden();
        $this->user->forceFill(['status' => 'suspended'])->save();
        $this->get('/panel/notification-email')->assertForbidden();
        $this->actingAs($this->admin);
        Livewire::test(ListEmailDeliveries::class)->assertSuccessful();
    }

    public function test_admin_cannot_enable_log_array_or_failover_transport(): void
    {
        $this->actingAs($this->admin);
        foreach (['log', 'array', 'failover'] as $mailer) {
            config(['mail.default' => $mailer]);
            $this->assertFalse(NotificationEmailSender::ready());
            Livewire::test(ManageEmailNotifications::class)->fillForm(['enabled' => true, 'per_user_daily' => 5, 'site_daily' => 1000])->call('save')->assertHasFormErrors(['enabled']);
        }
        config(['mail.default' => 'smtp']);
        Livewire::test(ManageEmailNotifications::class)->fillForm(['enabled' => true, 'per_user_daily' => 2, 'site_daily' => 50])->call('save')->assertHasNoFormErrors();
        $this->assertSame(2, app(EmailNotificationSettings::class)->refresh()->per_user_daily);
        Livewire::test(ManageEmailNotifications::class)->fillForm(['enabled' => true, 'per_user_daily' => 101, 'site_daily' => 10001])->call('save')->assertHasFormErrors(['per_user_daily', 'site_daily']);
    }

    public function test_generic_mail_has_fixed_trusted_links_unsubscribe_and_no_activity_payload(): void
    {
        $this->preference();
        $source = $this->notice();
        $p = EmailPreference::sole();
        $mail = new ActivityNotice($p->unsubscribe_token);
        $html = $mail->render();
        $this->assertStringContainsString('https://example.test/panel/notifications', $html);
        $this->assertStringContainsString('/notification-email/unsubscribe/'.$p->unsubscribe_token, $html);
        $this->assertStringNotContainsString($source->body, $html);
        $this->assertStringNotContainsString('evil.test', $html);
        $this->assertStringNotContainsString($this->user->email, $html);
        $this->assertSame('<https://example.test/notification-email/unsubscribe/'.$p->unsubscribe_token.'>', $mail->headers()->text['List-Unsubscribe']);
    }

    public function test_refund_decisions_enqueue_atomically_and_completion_replay_does_not_duplicate_notice(): void
    {
        $this->preference(['advertising']);
        $this->actingAs($this->admin);
        $invoice = AdvertisingInvoice::create(['user_id' => $this->user->id, 'description' => 'Email refund', 'amount' => '1000000']);
        $payment = AdvertisingPayment::recordReceived($invoice, '1000000', 'EMAIL-REFUND');
        $this->actingAs($this->user);
        $refund = AdvertisingRefundRequest::requestFor($payment, '200000', 'Reason', (string) Str::uuid());
        $this->actingAs($this->admin);
        $refund->decide('approve', 0);
        $refund->complete('EMAIL-REFUND-OUT', 1, true);
        $refund->complete('EMAIL-REFUND-OUT', 1, true);
        $this->assertSame(2, UserNotification::where('user_id', $this->user->id)->count());
        $this->assertSame(2, EmailDelivery::count());
        DB::beginTransaction();
        $this->notice('advertising');
        DB::rollBack();
        $this->assertSame(2, EmailDelivery::count());
    }

    public function test_command_bounds_and_non_acceptance_are_explicit(): void
    {
        $this->artisan('notifications:deliver-email', ['--limit' => 0])->assertExitCode(2);
        $this->preference();
        $this->notice();
        $sender = Mockery::mock(NotificationEmailSender::class);
        $sender->shouldReceive('send')->once()->andReturn(false);
        $this->instance(NotificationEmailSender::class, $sender);
        $this->artisan('notifications:deliver-email', ['--limit' => 1])->assertSuccessful();
        $this->assertSame('failed', EmailDelivery::sole()->state);
        $this->assertNull(EmailDelivery::sole()->accepted_at);
    }

    private function settings(bool $enabled, int $userLimit = 5, int $siteLimit = 1000): void
    {
        $s = app(EmailNotificationSettings::class);
        $s->enabled = $enabled;
        $s->per_user_daily = $userLimit;
        $s->site_daily = $siteLimit;
        $s->save();
    }

    private function preference(array $types = ['message']): EmailPreference
    {
        return EmailPreference::updateFor($this->user, true, $types, 0, true, 'fa');
    }

    private function notice(string $type = 'message'): UserNotification
    {
        return UserNotification::publish($this->user->id, $type, 'PRIVATE TITLE', 'PRIVATE BODY', 'https://evil.test/steal');
    }

    private function invalid(callable $operation, string $field): void
    {
        try {
            $operation();
            $this->fail('Expected validation exception');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey($field, $e->errors());
        }
    }
}
