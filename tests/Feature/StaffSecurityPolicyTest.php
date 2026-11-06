<?php

declare(strict_types=1);

namespace Tests\Feature;

use Filament\Facades\Filament;
use Illuminate\Auth\Events\Login;
use Illuminate\Routing\Events\RouteMatched;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\Sanctum;
use Livewire\Livewire;
use Modules\Admin\Filament\Pages\ManageStaffSecurity;
use Modules\Admin\Models\StaffSecurity;
use Modules\Admin\Models\StaffSecurityPolicy;
use Modules\Notification\Models\UserNotification;
use Modules\User\App\Models\User;
use PragmaRX\Google2FA\Google2FA;
use Tests\TestCase;

final class StaffSecurityPolicyTest extends TestCase
{
    private User $admin;

    private User $staff;

    private User $customer;

    private string $password = 'Staff-security-test-75!';

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create(['status' => 'active', 'password' => $this->password]);
        $this->admin->assignRole('admin');
        $this->staff = User::factory()->create(['status' => 'active', 'password' => $this->password]);
        $this->staff->assignRole('support');
        $this->customer = User::factory()->create(['status' => 'active']);
        $this->actingAs($this->admin);
        Filament::setCurrentPanel(Filament::getPanel('admin'));
    }

    public function test_default_policy_is_off_and_does_not_change_existing_access(): void
    {
        $this->assertFalse(StaffSecurityPolicy::current()->enabled);
        $this->get('/admin')->assertOk();
        $this->actingAs($this->staff);
        $this->flushSession();
        $this->get('/admin/operations')->assertOk();
        $this->actingAs($this->customer);
        $this->get('/panel')->assertOk();
        $this->get('/staff-security/setup')->assertForbidden();
    }

    public function test_activation_requires_password_and_prepared_admin_mfa(): void
    {
        $this->get('/admin/manage-staff-security')->assertOk();
        $this->invalid(fn () => StaffSecurityPolicy::change(true, ['support'], 15, 0, 'wrong'), 'data.current_password');
        $this->invalid(fn () => StaffSecurityPolicy::change(true, ['support'], 15, 0, $this->password), 'data.enabled');
        $this->seedMfa($this->admin);
        $this->prove($this->admin);
        $policy = StaffSecurityPolicy::change(true, ['support'], 15, 0, $this->password);
        $this->assertTrue($policy->enabled);
        $this->assertTrue($policy->requires($this->admin));
        $this->assertTrue(app(StaffSecurity::class)->hasProof($this->admin->fresh(), $policy));
        $this->invalid(fn () => StaffSecurityPolicy::change(false, [], 15, 0, $this->password), 'data.version');
        $this->assertSame(1, DB::table('staff_security_events')->where('kind', 'policy_changed')->count());
    }

    public function test_covered_unenrolled_staff_cannot_use_panel_customer_actions_or_livewire(): void
    {
        $this->policy();
        $this->actingAs($this->staff);
        $this->get('/admin/operations')->assertRedirect(route('staff-security.setup'));
        $this->get('/panel/support')->assertRedirect(route('staff-security.setup'));
        $this->post('/panel/support', ['subject' => 'Blocked', 'category' => 'technical', 'body' => 'No operation', 'form_key' => (string) Str::uuid()])->assertRedirect(route('staff-security.setup'));
        $this->postJson(route('default-livewire.update'), ['components' => []])->assertRedirect(route('staff-security.setup'));
        $this->assertSame(0, DB::table('support_tickets')->where('user_id', $this->staff->id)->count());
        $this->get('/staff-security/setup')->assertOk();
    }

    public function test_real_native_enrollment_uses_existing_breezy_secret_and_recovery_once(): void
    {
        $this->policy();
        $this->post('/staff-security/setup', ['current_password' => $this->password])->assertRedirect(route('staff-security.setup'));
        $source = app(StaffSecurity::class)->record($this->admin);
        $this->assertFalse((bool) $source->is_confirmed);
        $this->get('/staff-security/setup')->assertOk()->assertHeader('Referrer-Policy', 'no-referrer')->assertSee($source->two_factor_secret);
        $code = app(Google2FA::class)->getCurrentOtp($source->two_factor_secret);
        $this->post('/staff-security/enroll', ['code' => $code])->assertRedirect(route('staff-security.recovery'));
        $this->assertTrue((bool) $source->fresh()->is_confirmed);
        $this->assertTrue(app(StaffSecurity::class)->hasProof($this->admin->fresh()));
        $this->get('/staff-security/recovery')->assertOk()->assertSee($source->two_factor_recovery_codes[0]);
        $this->get('/staff-security/recovery')->assertRedirect('/admin');
        $this->get('/admin')->assertOk();
    }

    public function test_same_totp_cannot_authenticate_a_second_session(): void
    {
        $source = $this->seedMfa($this->admin);
        $code = app(Google2FA::class)->getCurrentOtp($source->two_factor_secret);
        $this->post('/staff-security/challenge', ['code' => $code])->assertRedirect();
        $this->withSession(['staff_security.proof' => null, 'breezy_session_id' => null]);
        $this->post('/staff-security/challenge', ['code' => $code])->assertSessionHasErrors('code');
        $this->assertFalse(app(StaffSecurity::class)->hasProof($this->admin->fresh()));
        $this->assertSame(1, DB::table('staff_security_states')->where('user_id', $this->admin->id)->value('failures'));
    }

    public function test_recovery_code_is_consumed_once_and_persian_totp_digits_are_accepted(): void
    {
        $source = $this->seedMfa($this->admin);
        $recovery = $source->two_factor_recovery_codes[0];
        $this->post('/staff-security/challenge', ['code' => $recovery])->assertRedirect();
        $this->assertNotContains($recovery, $source->fresh()->two_factor_recovery_codes);
        $this->withSession(['staff_security.proof' => null]);
        $this->post('/staff-security/challenge', ['code' => $recovery])->assertSessionHasErrors('code');
        $code = strtr(app(Google2FA::class)->getCurrentOtp($source->two_factor_secret), array_combine(str_split('0123456789'), preg_split('//u', '۰۱۲۳۴۵۶۷۸۹', -1, PREG_SPLIT_NO_EMPTY)));
        $this->post('/staff-security/challenge', ['code' => $code])->assertRedirect();
    }

    public function test_database_failure_budget_locks_all_sessions_then_resets_after_cooldown(): void
    {
        $source = $this->seedMfa($this->admin);
        for ($i = 0; $i < 5; $i++) {
            $this->post('/staff-security/challenge', ['code' => 'invalid'])->assertSessionHasErrors('code');
        }
        $recovery = $source->two_factor_recovery_codes[0];
        $this->post('/staff-security/challenge', ['code' => $recovery])->assertSessionHasErrors('code');
        $this->assertSame(5, DB::table('staff_security_states')->where('user_id', $this->admin->id)->value('failures'));
        $this->travel(11)->minutes();
        $this->post('/staff-security/challenge', ['code' => $recovery])->assertRedirect();
        $this->assertSame(0, DB::table('staff_security_states')->where('user_id', $this->admin->id)->value('failures'));
    }

    public function test_expired_sensitive_confirmation_blocks_mutation_until_password_and_fresh_code(): void
    {
        $this->seedMfa($this->admin);
        $this->prove($this->admin);
        $this->policy();
        $this->travel(16)->minutes();
        $this->get('/admin')->assertOk();
        $payload = ['subject' => 'Sensitive request', 'category' => 'technical', 'body' => 'After confirmation', 'form_key' => (string) Str::uuid()];
        $this->post('/panel/support', $payload)->assertRedirect(route('staff-security.challenge'));
        $this->assertSame(0, DB::table('support_tickets')->where('user_id', $this->admin->id)->count());
        $this->get('/staff-security/challenge')->assertOk()->assertSee('name="current_password"', false);
        $source = app(StaffSecurity::class)->record($this->admin);
        $code = $source->two_factor_recovery_codes[0];
        $this->post('/staff-security/challenge', ['code' => $code])->assertSessionHasErrors('current_password');
        $this->post('/staff-security/challenge', ['code' => $code, 'current_password' => $this->password])->assertRedirect();
        $this->post('/panel/support', $payload)->assertRedirect();
        $this->assertSame(1, DB::table('support_tickets')->where('user_id', $this->admin->id)->count());
    }

    public function test_proofs_expire_and_bind_user_policy_and_current_password_hash(): void
    {
        $this->seedMfa($this->admin);
        $this->prove($this->admin);
        $this->assertTrue(app(StaffSecurity::class)->hasProof($this->admin->fresh()));
        $this->admin->forceFill(['password' => 'Rotated-password-75!'])->save();
        $this->assertFalse(app(StaffSecurity::class)->hasProof($this->admin->fresh()));
        $this->prove($this->admin);
        $this->actingAs($this->staff);
        $this->assertFalse(app(StaffSecurity::class)->hasProof($this->staff));
        $this->actingAs($this->admin);
        $this->travel(13)->hours();
        $this->assertFalse(app(StaffSecurity::class)->hasProof($this->admin->fresh()));
    }

    public function test_new_login_event_clears_old_proof_and_legacy_breezy_marker(): void
    {
        $this->seedMfa($this->admin);
        $this->prove($this->admin);
        Event::dispatch(new Login('web', $this->admin, false));
        $this->assertNull(session('staff_security.proof'));
        $this->assertNull(session('breezy_session_id'));
    }

    public function test_mandatory_mfa_cannot_be_disabled_through_existing_model_or_plugin(): void
    {
        $this->seedMfa($this->admin);
        $this->policy();
        $this->invalid(fn () => $this->admin->fresh()->disableTwoFactorAuthentication(), 'current_password');
        $this->assertTrue((bool) app(StaffSecurity::class)->record($this->admin)->is_confirmed);
    }

    public function test_api_bearer_tokens_do_not_bypass_mandatory_staff_verification(): void
    {
        Route::middleware(['api', 'auth:sanctum'])->get('/api/staff-security-probe', fn () => response()->json(['ok' => true]));
        $this->policy();
        Sanctum::actingAs($this->staff, ['*']);
        $this->getJson('/api/staff-security-probe')->assertForbidden()->assertJsonPath('message', __('admin::security.verification_required'));
        Sanctum::actingAs($this->customer, ['*']);
        $this->getJson('/api/staff-security-probe')->assertOk();
    }

    public function test_unselected_staff_roles_and_ordinary_accounts_are_not_forced(): void
    {
        $this->policy([]);
        $this->actingAs($this->staff);
        $this->flushSession();
        $this->get('/admin/operations')->assertOk();
        $this->actingAs($this->customer);
        $this->get('/panel')->assertOk();
        $this->actingAs($this->admin);
        $this->get('/admin')->assertRedirect(route('staff-security.setup'));
    }

    public function test_public_callbacks_unsubscribe_and_logout_remain_available(): void
    {
        $this->policy();
        $this->get('/notification-email/unsubscribe/'.str_repeat('a', 64))->assertOk();
        $this->post('/notification-email/unsubscribe/'.str_repeat('a', 64))->assertOk();
        $this->post('/logout')->assertRedirect('/');
        $this->assertGuest();
    }

    public function test_return_destination_cannot_be_an_external_redirect(): void
    {
        $this->seedMfa($this->admin);
        $source = app(StaffSecurity::class)->record($this->admin);
        $this->withSession(['staff_security.return_to' => '//evil.test/path']);
        $this->post('/staff-security/challenge', ['code' => $source->two_factor_recovery_codes[0]])->assertRedirect('/admin');
    }

    public function test_new_context_is_private_metadata_only_and_not_a_raw_ip_or_user_agent_log(): void
    {
        $source = $this->seedMfa($this->admin);
        $this->withCookie('staff_security_device', str_repeat('a', 64));
        $this->withHeader('User-Agent', 'PRIVATE-AGENT-ONE')->post('/staff-security/challenge', ['code' => $source->two_factor_recovery_codes[0]])->assertRedirect();
        $this->assertSame(0, DB::table('staff_security_events')->where('kind', 'new_context')->count());
        $this->withSession(['staff_security.proof' => null]);
        $this->withCookie('staff_security_device', str_repeat('b', 64));
        $this->withHeader('User-Agent', 'PRIVATE-AGENT-TWO')->post('/staff-security/challenge', ['code' => $source->fresh()->two_factor_recovery_codes[0]])->assertRedirect();
        $this->assertSame(1, DB::table('staff_security_events')->where('kind', 'new_context')->count());
        $this->assertSame(1, UserNotification::where('user_id', $this->admin->id)->where('type', 'security')->count());
        $stored = json_encode(DB::table('staff_security_contexts')->get());
        $this->assertStringNotContainsString('PRIVATE-AGENT', $stored);
        $this->assertStringNotContainsString('127.0.0.1', $stored);
    }

    public function test_console_recovery_requires_confirmation_and_revokes_proof_without_changing_password(): void
    {
        $this->seedMfa($this->admin);
        $this->prove($this->admin);
        $hash = $this->admin->fresh()->password;
        $this->artisan('staff-security:recover', ['user' => $this->admin->id])->assertExitCode(2);
        $this->assertNotNull(app(StaffSecurity::class)->record($this->admin));
        $this->artisan('staff-security:recover', ['user' => $this->admin->id, '--confirm' => true])->assertSuccessful();
        $this->assertNull(app(StaffSecurity::class)->record($this->admin));
        $this->assertFalse(app(StaffSecurity::class)->hasProof($this->admin->fresh()));
        $this->assertSame($hash, $this->admin->fresh()->password);
        $this->assertSame(1, DB::table('staff_security_events')->where('kind', 'operator_reset')->count());
    }

    public function test_settings_are_full_admin_only_and_real_filament_save_uses_current_version(): void
    {
        $this->seedMfa($this->admin);
        $this->prove($this->admin);
        Event::listen(RouteMatched::class, fn ($event) => $event->request->setLaravelSession(app('session')->driver()));
        Livewire::test(ManageStaffSecurity::class)->fillForm(['enabled' => true, 'roles' => ['support'], 'step_up_minutes' => 15, 'version' => 0, 'current_password' => $this->password])->call('save')->assertHasNoFormErrors();
        $this->assertTrue(StaffSecurityPolicy::current()->enabled);
        $this->assertTrue(app(StaffSecurity::class)->hasProof($this->admin->fresh()));
        $this->actingAs($this->staff);
        $this->get('/admin/manage-staff-security')->assertRedirect(route('staff-security.setup'));
        $this->policy([]);
        $this->get('/admin/manage-staff-security')->assertForbidden();
        $this->get('/admin/staff-security-events')->assertForbidden();
    }

    public function test_pending_enrollment_secret_is_bound_to_the_password_verified_session(): void
    {
        $this->post('/staff-security/setup', ['current_password' => $this->password])->assertRedirect();
        $source = app(StaffSecurity::class)->record($this->admin);
        $secret = $source->two_factor_secret;
        $this->withSession(['staff_security.enrollment' => null]);
        $this->get('/staff-security/setup')->assertOk()->assertDontSee($secret);
        $this->post('/staff-security/enroll', ['code' => app(Google2FA::class)->getCurrentOtp($secret)])->assertSessionHasErrors('code');
        $this->assertFalse((bool) $source->fresh()->is_confirmed);
    }

    public function test_enrollment_password_capability_expires_without_confirming_the_secret(): void
    {
        $this->post('/staff-security/setup', ['current_password' => $this->password])->assertRedirect();
        $source = app(StaffSecurity::class)->record($this->admin);
        $this->travel(11)->minutes();
        $this->get('/staff-security/setup')->assertOk()->assertDontSee($source->two_factor_secret);
        $this->post('/staff-security/enroll', ['code' => app(Google2FA::class)->getCurrentOtp($source->two_factor_secret)])->assertSessionHasErrors('code');
    }

    public function test_policy_changes_cannot_extend_the_original_mfa_verification_time(): void
    {
        $this->seedMfa($this->admin);
        $this->prove($this->admin);
        $at = session('staff_security.proof.at');
        $this->travel(10)->minutes();
        StaffSecurityPolicy::change(true, ['support'], 15, 0, $this->password);
        $this->assertSame($at, session('staff_security.proof.at'));
        $this->travel(6)->minutes();
        $this->assertFalse(app(StaffSecurity::class)->recent($this->admin->fresh(), StaffSecurityPolicy::current()));
        $this->get('/staff-security/setup')->assertRedirect(route('staff-security.challenge'));
        $this->invalid(fn () => StaffSecurityPolicy::change(false, [], 15, 1, $this->password), 'data.enabled');
    }

    private function policy(array $roles = ['support', 'panel_access']): void
    {
        DB::table('staff_security_policies')->where('id', 1)->update(['enabled' => true, 'roles' => json_encode($roles)]);
    }

    private function seedMfa(User $user): mixed
    {
        return $user->breezySessions()->create(['panel_id' => 'admin', 'two_factor_secret' => app(Google2FA::class)->generateSecretKey(), 'two_factor_confirmed_at' => now(), 'two_factor_recovery_codes' => array_map(fn () => Str::random(10).'-'.Str::random(10), range(1, 8))]);
    }

    private function prove(User $user): void
    {
        $this->actingAs($user);
        $source = app(StaffSecurity::class)->record($user);
        $this->post('/staff-security/challenge', ['code' => $source->two_factor_recovery_codes[0]])->assertRedirect();
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
