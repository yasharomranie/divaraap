<?php

declare(strict_types=1);

namespace Tests\Feature;

use Filament\Facades\Filament;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Modules\Notification\Models\EmailDelivery;
use Modules\Notification\Models\EmailPreference;
use Modules\Notification\Models\UserNotification;
use Modules\Site\App\Settings\EmailNotificationSettings;
use Modules\Support\Filament\Admin\Resources\SupportTicketResource\Pages\ListSupportTickets;
use Modules\Support\Filament\Admin\Resources\SupportTicketResource\Pages\ViewSupportTicket;
use Modules\Support\Models\SupportMessage;
use Modules\Support\Models\SupportTicket;
use Modules\User\App\Models\User;
use Spatie\Activitylog\Models\Activity;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

final class SupportTicketsTest extends TestCase
{
    private User $customer;

    private User $admin;

    private User $staff;

    private User $otherStaff;

    protected function setUp(): void
    {
        parent::setUp();
        $this->customer = User::factory()->create(['status' => 'active', 'email_verified_at' => now()]);
        $this->admin = User::where('email', 'a@a.com')->firstOrFail();
        $this->staff = User::factory()->create(['status' => 'active']);
        $this->staff->assignRole('support');
        $this->otherStaff = User::factory()->create(['status' => 'active']);
        $this->otherStaff->assignRole('support');
        $this->actingAs($this->customer);
        Filament::setCurrentPanel(Filament::getPanel('admin'));
    }

    public function test_creation_is_private_encrypted_immutable_and_idempotent(): void
    {
        $key = (string) Str::uuid();
        $ticket = SupportTicket::openTicket('PRIVATE SUBJECT', 'technical', 'PRIVATE BODY', $key);
        $this->assertSame('open', $ticket->state);
        $this->assertSame(0, $ticket->version);
        $this->assertSame(1, $ticket->messages()->count());
        $this->assertSame($ticket->id, SupportTicket::openTicket('PRIVATE SUBJECT', 'technical', 'PRIVATE BODY', $key)->id);
        $this->invalid(fn () => SupportTicket::openTicket('Changed', 'technical', 'PRIVATE BODY', $key), 'form_key');
        $this->assertNotSame('PRIVATE SUBJECT', DB::table('support_tickets')->where('id', $ticket->id)->value('subject'));
        $this->assertNotSame('PRIVATE BODY', DB::table('support_messages')->where('ticket_id', $ticket->id)->value('body'));
        $this->assertArrayNotHasKey('subject', $ticket->toArray());
        $this->assertArrayNotHasKey('body', $ticket->messages()->sole()->toArray());
        $this->assertStringNotContainsString('PRIVATE', Activity::whereIn('subject_type', [SupportTicket::class, SupportMessage::class])->get()->toJson());
        $this->invalid(fn () => $ticket->update(['state' => 'closed']), 'ticket');
        $this->invalid(fn () => $ticket->delete(), 'ticket');
        $this->invalid(fn () => $ticket->messages()->sole()->update(['body' => 'Altered']), 'ticket');
        $this->invalid(fn () => $ticket->messages()->sole()->delete(), 'ticket');
    }

    public function test_open_and_daily_limits_are_independent_and_creation_validation_is_strict(): void
    {
        $this->invalid(fn () => SupportTicket::openTicket(' ', 'technical', 'Message', (string) Str::uuid()), 'subject');
        $this->invalid(fn () => SupportTicket::openTicket('Subject', 'invalid', 'Message', (string) Str::uuid()), 'category');
        $this->invalid(fn () => SupportTicket::openTicket('Subject', 'technical', str_repeat('x', 4001), (string) Str::uuid()), 'body');
        for ($i = 0; $i < 5; $i++) {
            $this->ticket();
        }
        $this->invalid(fn () => $this->ticket(), 'ticket');
        foreach (SupportTicket::all() as $ticket) {
            $ticket->write('closed', 'Close', 0, (string) Str::uuid());
        }
        for ($i = 0; $i < 5; $i++) {
            $this->ticket()->write('closed', 'Close', 0, (string) Str::uuid());
        }
        $this->invalid(fn () => $this->ticket(), 'ticket');
        $this->assertSame(10, SupportTicket::count());
    }

    public function test_customer_and_unrelated_staff_cannot_read_foreign_assigned_tickets(): void
    {
        $ticket = $this->ticket();
        $other = User::factory()->create(['status' => 'active']);
        $this->actingAs($other);
        $this->get('/panel/support/'.$ticket->id)->assertNotFound();
        $this->post('/panel/support/'.$ticket->id, $this->payload(0))->assertNotFound();
        $this->actingAs($this->admin);
        $ticket->assign($this->staff->id, 0);
        $this->actingAs($this->otherStaff);
        $this->get('/admin/support-tickets/'.$ticket->id)->assertNotFound();
        $this->assertSame(0, SupportTicket::staffQuery()->count());
        $this->forbidden(fn () => $ticket->write('staff', 'Unauthorized', 1, (string) Str::uuid(), true), 404);
        $this->actingAs($this->staff);
        $this->get('/admin/support-tickets/'.$ticket->id)->assertOk();
    }

    public function test_claim_requires_an_unassigned_ticket_and_other_staff_cannot_take_it(): void
    {
        $ticket = $this->ticket();
        $this->actingAs($this->staff);
        $this->forbidden(fn () => $ticket->write('staff', 'Claim first', 0, (string) Str::uuid(), true), 404);
        $claimed = $ticket->assign($this->staff->id, 0);
        $this->assertSame($this->staff->id, $claimed->assignee_id);
        $this->assertSame(1, $claimed->version);
        $this->actingAs($this->otherStaff);
        $this->forbidden(fn () => $ticket->assign($this->otherStaff->id, 1), 404);
    }

    public function test_admin_assignment_checks_active_staff_scope_and_stale_versions(): void
    {
        $ticket = $this->ticket();
        $this->actingAs($this->admin);
        $this->invalid(fn () => $ticket->assign($this->customer->id, 0), 'version');
        $this->invalid(fn () => $ticket->assign(999999999, 0), 'version');
        $assigned = $ticket->assign($this->staff->id, 0);
        $this->invalid(fn () => $ticket->assign($this->otherStaff->id, 0), 'version');
        $this->assertSame($this->staff->id, $assigned->assignee_id);
        $ticket->assign($this->otherStaff->id, 1);
        $this->actingAs($this->staff);
        $this->get('/admin/support-tickets/'.$ticket->id)->assertNotFound();
        $this->actingAs($this->admin);
        $this->otherStaff->forceFill(['status' => 'suspended'])->save();
        $this->invalid(fn () => $ticket->assign($this->otherStaff->id, 2), 'version');
        $this->assertSame(2, UserNotification::where('type', 'support')->count());
    }

    public function test_public_reply_customer_response_and_resolution_have_consistent_states(): void
    {
        $ticket = $this->ticket();
        $this->actingAs($this->admin);
        $ticket = $ticket->write('staff', 'Public response', 0, (string) Str::uuid(), true);
        $this->assertSame('waiting_customer', $ticket->state);
        $this->assertSame(1, UserNotification::where('user_id', $this->customer->id)->count());
        $this->actingAs($this->customer);
        $ticket = $ticket->write('customer', 'Follow up', 1, (string) Str::uuid());
        $this->assertSame('open', $ticket->state);
        $this->actingAs($this->admin);
        $ticket = $ticket->write('resolved', 'Solution explained', 2, (string) Str::uuid(), true);
        $this->assertSame('resolved', $ticket->state);
        $this->assertNotNull($ticket->resolved_at);
        $this->invalid(fn () => $ticket->write('staff', 'Wrong state', 3, (string) Str::uuid(), true), 'version');
    }

    public function test_internal_notes_never_reach_customer_views_notifications_or_email_payloads(): void
    {
        $ticket = $this->ticket();
        $this->actingAs($this->admin);
        $ticket = $ticket->write('internal', 'INTERNAL SECRET ONLY', 0, (string) Str::uuid(), true);
        $this->assertSame('open', $ticket->state);
        $this->assertSame(0, UserNotification::where('type', 'support')->count());
        $this->assertSame(0, EmailDelivery::count());
        $this->get('/admin/support-tickets/'.$ticket->id)->assertOk()->assertSee('INTERNAL SECRET ONLY');
        $this->actingAs($this->customer);
        $this->get('/panel/support/'.$ticket->id)->assertOk()->assertDontSee('INTERNAL SECRET ONLY')->assertDontSee(__('support::messages.internal'));
        $this->post('/panel/support/'.$ticket->id, $this->payload(1) + ['staffSide' => true, 'kind' => 'internal'])->assertRedirect();
        $this->assertSame('customer', $ticket->messages()->latest('id')->first()->kind);
    }

    public function test_exact_operation_replay_does_not_duplicate_messages_or_notifications(): void
    {
        $ticket = $this->ticket();
        $this->actingAs($this->admin);
        $key = (string) Str::uuid();
        $ticket->write('staff', 'Same reply', 0, $key, true);
        $ticket->write('staff', 'Same reply', 0, $key, true);
        $this->assertSame(2, $ticket->messages()->count());
        $this->assertSame(1, UserNotification::where('type', 'support')->count());
        $this->invalid(fn () => $ticket->write('staff', 'Changed payload', 1, $key, true), 'operation_key');
        $this->invalid(fn () => $ticket->write('staff', 'Stale reply', 0, (string) Str::uuid(), true), 'version');
    }

    public function test_customer_reopening_requires_a_recent_resolution_and_is_bounded(): void
    {
        $ticket = $this->ticket();
        for ($i = 0; $i < 3; $i++) {
            $this->actingAs($this->admin);
            $ticket = $ticket->write('resolved', 'Resolved', $ticket->version, (string) Str::uuid(), true);
            $this->actingAs($this->customer);
            $ticket = $ticket->write('reopened', 'Still unresolved', $ticket->version, (string) Str::uuid());
            $this->assertSame('open', $ticket->state);
        }
        $this->actingAs($this->admin);
        $ticket = $ticket->write('resolved', 'Resolved again', $ticket->version, (string) Str::uuid(), true);
        $this->actingAs($this->customer);
        $this->invalid(fn () => $ticket->write('reopened', 'More', $ticket->version, (string) Str::uuid()), 'ticket');
        $fresh = $this->ticket();
        $this->actingAs($this->admin);
        $fresh = $fresh->write('resolved', 'Old solution', 0, (string) Str::uuid(), true);
        DB::table('support_tickets')->where('id', $fresh->id)->update(['resolved_at' => now()->subDays(8)]);
        $this->actingAs($this->customer);
        $this->invalid(fn () => $fresh->write('reopened', 'Too late', 1, (string) Str::uuid()), 'ticket');
    }

    public function test_confirmed_customer_closure_is_final_and_cannot_be_replayed_as_reopening(): void
    {
        $ticket = $this->ticket();
        $payload = $this->payload(0);
        $payload['action'] = 'closed';
        $this->post('/panel/support/'.$ticket->id, $payload)->assertSessionHasErrors('confirmed');
        $payload['confirmed'] = 1;
        $this->post('/panel/support/'.$ticket->id, $payload)->assertRedirect();
        $this->assertSame('closed', $ticket->fresh()->state);
        $this->post('/panel/support/'.$ticket->id, $payload)->assertRedirect();
        $this->assertSame(2, $ticket->messages()->count());
        $this->invalid(fn () => $ticket->write('reopened', 'Reopen', 1, (string) Str::uuid()), 'version');
        $this->actingAs($this->admin);
        $this->invalid(fn () => $ticket->write('staff', 'Reply', 1, (string) Str::uuid(), true), 'version');
    }

    public function test_members_and_staff_cannot_promote_own_customer_ticket_to_staff_response(): void
    {
        $ticket = $this->ticket();
        $this->post('/panel/support/'.$ticket->id, array_replace($this->payload(0), ['action' => 'internal']))->assertSessionHasErrors('action');
        $this->forbidden(fn () => $ticket->write('internal', 'Forged', 0, (string) Str::uuid()), 403);
        $this->actingAs($this->staff);
        $own = $this->ticket();
        $this->forbidden(fn () => $own->write('staff', 'Self review', 0, (string) Str::uuid(), true), 404);
        $this->assertFalse($own->canWork($this->staff));
    }

    public function test_native_creation_ignores_forged_actor_assignment_status_and_body_is_escaped(): void
    {
        $key = (string) Str::uuid();
        $payload = ['subject' => 'Native subject', 'category' => 'billing', 'body' => '<script>alert(1)</script>', 'form_key' => $key, 'user_id' => $this->admin->id, 'assignee_id' => $this->admin->id, 'state' => 'resolved'];
        $this->post('/panel/support', $payload)->assertRedirect();
        $ticket = SupportTicket::sole();
        $this->assertSame($this->customer->id, $ticket->user_id);
        $this->assertNull($ticket->assignee_id);
        $this->assertSame('open', $ticket->state);
        $this->get('/panel/support/'.$ticket->id)->assertOk()->assertHeader('Cache-Control', 'no-store, private')->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false)->assertDontSee('<script>alert(1)</script>', false);
        $this->post('/panel/support', $payload)->assertRedirect();
        $this->assertSame(1, SupportTicket::count());
        $this->get('/panel/support/not-an-id')->assertNotFound();
    }

    public function test_assignment_revocation_inactive_users_and_staff_role_removal_are_enforced(): void
    {
        $ticket = $this->ticket();
        $this->actingAs($this->admin);
        $ticket->assign($this->staff->id, 0);
        $this->staff->syncRoles([]);
        $this->actingAs($this->staff);
        $this->get('/admin/support-tickets')->assertForbidden();
        $this->forbidden(fn () => $ticket->write('staff', 'Old role', 1, (string) Str::uuid(), true), 404);
        $this->customer->forceFill(['status' => 'suspended'])->save();
        $this->actingAs($this->customer);
        $this->get('/panel/support')->assertForbidden();
        auth()->logout();
        $this->get('/panel/support')->assertRedirect('/login');
    }

    public function test_filament_claim_reply_note_resolution_and_assignment_use_model_guards(): void
    {
        $ticket = $this->ticket();
        $this->actingAs($this->staff);
        Livewire::test(ListSupportTickets::class)->assertSuccessful()->assertCanSeeTableRecords([$ticket]);
        Livewire::test(ViewSupportTicket::class, ['record' => $ticket->id])->callAction('claim', ['version' => 0])->assertHasNoActionErrors();
        Livewire::test(ViewSupportTicket::class, ['record' => $ticket->id])->callAction('staff', ['version' => 1, 'operation_key' => (string) Str::uuid(), 'body' => 'Filament reply'])->assertHasNoActionErrors();
        Livewire::test(ViewSupportTicket::class, ['record' => $ticket->id])->callAction('internal', ['version' => 2, 'operation_key' => (string) Str::uuid(), 'body' => 'Filament internal'])->assertHasNoActionErrors();
        Livewire::test(ViewSupportTicket::class, ['record' => $ticket->id])->callAction('resolved', ['version' => 3, 'operation_key' => (string) Str::uuid(), 'body' => 'Filament solution'])->assertHasNoActionErrors();
        $this->assertSame('resolved', $ticket->fresh()->state);
        $this->actingAs($this->admin);
        Livewire::test(ViewSupportTicket::class, ['record' => $ticket->id])->callAction('assign', ['version' => 4, 'assignee_id' => $this->otherStaff->id])->assertHasNoActionErrors();
        $this->assertSame($this->otherStaff->id, $ticket->fresh()->assignee_id);
    }

    public function test_support_email_is_opt_in_and_never_contains_the_ticket_body(): void
    {
        config(['mail.default' => 'smtp', 'app.url' => 'https://example.test', 'mail.from.address' => 'notice@example.test']);
        $settings = app(EmailNotificationSettings::class);
        $settings->enabled = true;
        $settings->save();
        EmailPreference::updateFor($this->customer, true, ['message'], 0, true, 'fa');
        $ticket = $this->ticket();
        $this->actingAs($this->admin);
        $ticket = $ticket->write('staff', 'PRIVATE RESPONSE', 0, (string) Str::uuid(), true);
        $this->assertSame(0, EmailDelivery::count());
        $this->actingAs($this->customer);
        EmailPreference::updateFor($this->customer, true, ['support'], 1, true, 'fa');
        $this->actingAs($this->admin);
        $ticket->write('staff', 'ANOTHER PRIVATE RESPONSE', 1, (string) Str::uuid(), true);
        $this->assertSame(1, EmailDelivery::count());
        $this->assertStringNotContainsString('PRIVATE', UserNotification::where('user_id', $this->customer->id)->get()->toJson());
        $this->assertStringNotContainsString('PRIVATE', EmailDelivery::sole()->toJson());
    }

    public function test_transaction_rollback_leaves_no_partial_ticket_or_notifications(): void
    {
        DB::beginTransaction();
        $ticket = $this->ticket();
        $this->actingAs($this->admin);
        $ticket->write('staff', 'Rollback reply', 0, (string) Str::uuid(), true);
        DB::rollBack();
        $this->assertSame(0, SupportTicket::count());
        $this->assertSame(0, SupportMessage::count());
        $this->assertSame(0, UserNotification::where('type', 'support')->count());
    }

    public function test_customer_thread_excludes_internal_notes_from_bounded_pagination(): void
    {
        $ticket = $this->ticket();
        $this->actingAs($this->admin);
        for ($i = 0; $i < 21; $i++) {
            $ticket = $ticket->write('internal', 'HIDDEN NOTE '.$i, $ticket->version, (string) Str::uuid(), true);
        }
        $this->actingAs($this->customer);
        $this->get('/panel/support/'.$ticket->id)->assertOk()->assertDontSee('HIDDEN NOTE')->assertSee('First request');
        $this->get('/panel/support/'.$ticket->id.'?page=2')->assertOk()->assertDontSee('HIDDEN NOTE');
    }

    public function test_message_limit_still_allows_resolution_and_final_closure(): void
    {
        $ticket = $this->ticket();
        $body = encrypt('Limit fixture', false);
        $rows = [];
        for ($i = 0; $i < 999; $i++) {
            $rows[] = ['ticket_id' => $ticket->id, 'author_id' => $this->customer->id, 'kind' => 'customer', 'body' => $body, 'operation_key' => (string) Str::uuid(), 'created_at' => now(), 'updated_at' => now()];
        }
        DB::table('support_messages')->insert($rows);
        $this->invalid(fn () => $ticket->write('customer', 'Over limit', 0, (string) Str::uuid()), 'ticket');
        $this->actingAs($this->admin);
        $ticket = $ticket->write('resolved', 'Final solution', 0, (string) Str::uuid(), true);
        $this->assertSame('resolved', $ticket->state);
        $ticket = $ticket->write('closed', 'Final closure', 1, (string) Str::uuid(), true);
        $this->assertSame('closed', $ticket->state);
        $this->assertSame(1002, $ticket->messages()->count());
    }

    public function test_stale_native_reply_preserves_body_and_uses_the_refreshed_revision(): void
    {
        $ticket = $this->ticket();
        $this->actingAs($this->admin);
        $ticket->write('staff', 'New staff message', 0, (string) Str::uuid(), true);
        $this->actingAs($this->customer);
        $payload = $this->payload(0);
        $this->from('/panel/support/'.$ticket->id)->post('/panel/support/'.$ticket->id, $payload)->assertSessionHasErrors('version');
        $this->get('/panel/support/'.$ticket->id)->assertOk()->assertSee('Native follow-up')->assertSee('name="version" value="1"', false);
        $payload['version'] = 1;
        $this->post('/panel/support/'.$ticket->id, $payload)->assertRedirect();
        $this->assertSame(2, $ticket->fresh()->version);
    }

    private function ticket(): SupportTicket
    {
        return SupportTicket::openTicket('Help with my account', 'technical', 'First request', (string) Str::uuid());
    }

    private function payload(int $version): array
    {
        return ['action' => 'customer', 'body' => 'Native follow-up', 'version' => $version, 'operation_key' => (string) Str::uuid()];
    }

    private function invalid(callable $operation, string $field): void
    {
        try {
            $operation();
            $this->fail('Expected validation failure');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey($field, $e->errors());
        }
    }

    private function forbidden(callable $operation, int $status): void
    {
        try {
            $operation();
            $this->fail('Expected access denial');
        } catch (HttpException $e) {
            $this->assertSame($status, $e->getStatusCode());
        } catch (ModelNotFoundException) {
            $this->assertSame(404, $status);
        }
    }
}
