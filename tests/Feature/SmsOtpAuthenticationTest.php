<?php

declare(strict_types=1);

namespace Tests\Feature;

use Filament\Facades\Filament;
use Illuminate\Http\Client\Factory;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Modules\Site\App\Settings\AuthenticationSettings;
use Modules\Site\Filament\Admin\Pages\ManageAuthentication;
use Modules\User\App\Models\PhoneOtpChallenge;
use Modules\User\App\Models\PhoneOtpLimit;
use Modules\User\App\Models\Profile;
use Modules\User\App\Models\SocialiteUser;
use Modules\User\App\Models\User;
use Modules\User\App\Models\UserPhoneIdentity;
use Modules\User\App\Support\AuthenticationMethods;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

final class SmsOtpAuthenticationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $settings = app(AuthenticationSettings::class);
        $settings->login_methods = ['email', 'sms'];
        $settings->registration_methods = ['email', 'sms'];
        $settings->smsir_api_key = 'otp-test-private-key';
        $settings->smsir_template_id = 123456;
        $settings->smsir_parameter = 'CODE';
        $settings->sms_daily_limit = 200;
        $settings->save();
        Http::preventStrayRequests();
        Http::fake(['api.sms.ir/*' => Http::response(['status' => 1, 'data' => ['messageId' => 98765]])]);
    }

    public function test_sms_registration_uses_the_official_api_and_creates_a_verified_phone_account_without_fake_email(): void
    {
        Notification::fake();
        $this->get('/auth/phone/register?redirect=/panel/my-profile')->assertOk();
        $this->post('/auth/phone/register', $this->registration())->assertRedirect('/auth/phone/register');
        $challenge = PhoneOtpChallenge::latest()->firstOrFail();
        $code = $this->code();
        Http::assertSent(fn ($request): bool => $request->url() === 'https://api.sms.ir/v1/send/verify'
            && $request->hasHeader('X-API-KEY', 'otp-test-private-key') && $request['mobile'] === '09123456789'
            && $request['templateId'] === 123456 && $request['parameters'][0]['name'] === 'CODE');
        $raw = DB::table('phone_otp_challenges')->where('id', $challenge->id)->first();
        $this->assertStringNotContainsString($code, $raw->code_hash);
        $this->assertStringNotContainsString('9123456789', $raw->phone);
        $this->assertStringNotContainsString('Test', $raw->payload);
        $this->post('/auth/phone/register/verify', ['code' => $code])->assertRedirect('/panel/my-profile');
        $user = Auth::user();
        $this->assertNull($user->email);
        $this->assertSame('Test Person', $user->name);
        $this->assertSame('+989123456789', UserPhoneIdentity::forUser($user)->phone);
        $this->assertSame('+989123456789', Profile::phoneForUser($user));
        $this->get('/panel/my-profile')->assertOk();
        $this->actingAs($user->fresh());
        $this->patch('/panel/my-profile', ['name' => 'Edited', 'email' => null])->assertSessionHasNoErrors();
        $this->post('/email/verification-notification')->assertStatus(422);
        Notification::assertNothingSent();
        $this->assertNotNull($challenge->fresh()->consumed_at);
    }

    public function test_defaults_and_email_login_remain_usable_when_sms_is_disabled(): void
    {
        $this->disableSms();
        $this->get('/login')->assertOk()->assertSee('id="email"', false)->assertDontSee('/auth/phone/login', false);
        $this->get('/auth/phone/login')->assertForbidden();
        $this->post('/auth/phone/login', ['phone' => '09123456789'])->assertForbidden();
        Http::assertNothingSent();
    }

    public function test_sms_only_policy_hides_email_forms_and_blocks_direct_email_posts(): void
    {
        $settings = app(AuthenticationSettings::class);
        $settings->login_methods = ['sms'];
        $settings->registration_methods = ['sms'];
        $settings->save();
        $this->get('/login')->assertOk()->assertDontSee('id="email"', false)->assertSee('/auth/phone/login', false);
        $this->get('/register')->assertOk()->assertDontSee('id="password"', false);
        $this->post('/login', ['email' => 'b@b.com', 'password' => 'password'])->assertForbidden();
        $this->post('/register', [])->assertForbidden();
        $this->get('/admin/login')->assertOk();
    }

    public function test_terms_and_valid_iranian_number_are_required_before_sending(): void
    {
        $data = $this->registration();
        unset($data['terms']);
        $this->post('/auth/phone/register', $data)->assertSessionHasErrors('terms');
        $this->post('/auth/phone/login', ['phone' => '+12025550123'])->assertSessionHasErrors('phone');
        Http::assertNothingSent();
    }

    public function test_wrong_codes_are_counted_and_five_attempts_lock_the_challenge(): void
    {
        $this->post('/auth/phone/register', $this->registration());
        $id = session('phone_otp.id');
        $code = $this->code();
        for ($i = 0; $i < 5; $i++) {
            $this->post('/auth/phone/register/verify', ['code' => '000000'])->assertSessionHasErrors('code');
        }
        $this->assertSame(5, PhoneOtpChallenge::findOrFail($id)->attempts);
        $this->post('/auth/phone/register/verify', ['code' => $code])->assertSessionHasErrors('code');
        $this->assertGuest();
        $this->assertFalse(UserPhoneIdentity::where('phone', '+989123456789')->exists());
        $this->assertArrayNotHasKey('code', session()->getOldInput());
    }

    public function test_expired_and_cross_session_codes_cannot_authenticate(): void
    {
        $this->post('/auth/phone/register', $this->registration());
        $id = session('phone_otp.id');
        $code = $this->code();
        $nonce = session('phone_otp.nonce');
        $this->withSession(['phone_otp' => ['id' => $id, 'nonce' => 'another-browser']])
            ->post('/auth/phone/register/verify', ['code' => $code])->assertSessionHasErrors('code');
        $this->withSession(['phone_otp' => ['id' => $id, 'nonce' => $nonce]]);
        $this->travel(6)->minutes();
        $this->post('/auth/phone/register/verify', ['code' => $code])->assertSessionHasErrors('code');
        $this->assertGuest();
    }

    public function test_resend_has_database_cooldown_and_invalidates_the_previous_code(): void
    {
        $this->post('/auth/phone/register', $this->registration());
        $old = PhoneOtpChallenge::findOrFail(session('phone_otp.id'));
        $oldCode = $this->code();
        $this->post('/auth/phone/register/resend')->assertSessionHasErrors('phone');
        Http::assertSentCount(1);
        $this->travel(61)->seconds();
        $this->post('/auth/phone/register/resend')->assertRedirect('/auth/phone/register');
        Http::assertSentCount(2);
        $this->assertNotNull($old->fresh()->consumed_at);
        $this->assertNotSame($old->id, session('phone_otp.id'));
        $this->assertFalse($old->fresh()->code_hash === PhoneOtpChallenge::findOrFail(session('phone_otp.id'))->code_hash);
        $this->assertSame(6, strlen($oldCode));
    }

    public function test_global_budget_and_phone_hour_limit_are_enforced_without_redis(): void
    {
        $settings = app(AuthenticationSettings::class);
        $settings->sms_daily_limit = 1;
        $settings->save();
        $this->post('/auth/phone/login', ['phone' => '09123456789'])->assertRedirect();
        $this->post('/auth/phone/login', ['phone' => '09123456788'])->assertSessionHasErrors('phone');
        Http::assertSentCount(1);
        $this->assertGreaterThanOrEqual(6, PhoneOtpLimit::count());
    }

    public function test_provider_failure_does_not_create_a_usable_code_or_expose_provider_secrets(): void
    {
        Http::swap(new Factory);
        Http::preventStrayRequests();
        Http::fake(['api.sms.ir/*' => Http::response(['status' => 102, 'message' => 'secret-provider-response'], 400)]);
        $this->post('/auth/phone/register', $this->registration())->assertSessionHasErrors('phone');
        $challenge = PhoneOtpChallenge::latest()->firstOrFail();
        $this->assertFalse($challenge->sent);
        $this->assertNotNull($challenge->consumed_at);
        $this->assertStringNotContainsString('secret-provider-response', json_encode(session('errors')));
        $this->assertGuest();
        Http::assertSentCount(1);
    }

    public function test_transport_failure_is_not_retried(): void
    {
        Http::swap(new Factory);
        Http::preventStrayRequests();
        Http::fake(['api.sms.ir/*' => Http::failedConnection()]);
        $this->post('/auth/phone/register', $this->registration())->assertSessionHasErrors('phone');
        $this->assertFalse(PhoneOtpChallenge::latest()->firstOrFail()->sent);
    }

    public function test_profile_contact_number_does_not_grant_login_or_get_auto_linked(): void
    {
        $user = User::where('email', 'b@b.com')->firstOrFail();
        Profile::savePhoneForUser($user, '09123456789');
        $this->post('/auth/phone/login', ['phone' => '09123456789'])->assertRedirect();
        $this->post('/auth/phone/login/verify', ['code' => $this->code()])->assertSessionHasErrors('code');
        $this->assertGuest();
        $this->assertNull(UserPhoneIdentity::forUser($user));
    }

    public function test_existing_member_can_bind_with_password_and_then_login_using_persian_digits(): void
    {
        $user = User::where('email', 'b@b.com')->firstOrFail();
        $user->updatePassword('binding-test-password');
        $this->actingAs($user)->get('/panel/phone')->assertOk();
        $this->post('/panel/phone', ['phone' => '۰۹۱۲۳۴۵۶۷۸۹', 'current_password' => 'wrong'])->assertSessionHasErrors('current_password');
        Http::assertNothingSent();
        $this->post('/panel/phone', ['phone' => '۰۹۱۲۳۴۵۶۷۸۹', 'current_password' => 'binding-test-password'])->assertRedirect('/panel/phone');
        $this->post('/panel/phone/verify', ['code' => $this->code()])->assertRedirect('/panel/my-profile');
        $this->assertSame('+989123456789', UserPhoneIdentity::forUser($user)->phone);
        $this->post('/logout');
        $this->travel(61)->seconds();
        $this->post('/auth/phone/login', ['phone' => '+989123456789'])->assertRedirect();
        $code = strtr($this->code(), ['0' => '۰', '1' => '۱', '2' => '۲', '3' => '۳', '4' => '۴', '5' => '۵', '6' => '۶', '7' => '۷', '8' => '۸', '9' => '۹']);
        $this->post('/auth/phone/login/verify', ['code' => $code])->assertRedirect('/dashboard');
        $this->assertAuthenticatedAs($user);
    }

    public function test_sms_cannot_bypass_admin_or_mfa_protection(): void
    {
        $admin = User::where('email', 'a@a.com')->firstOrFail();
        $this->actingAs($admin)->get('/panel/phone')->assertForbidden();
        UserPhoneIdentity::create(['user_id' => $admin->id, 'phone' => '+989123456789', 'verified_at' => now()]);
        $this->post('/logout');
        $this->post('/auth/phone/login', ['phone' => '09123456789']);
        $this->post('/auth/phone/login/verify', ['code' => $this->code()])->assertSessionHasErrors('code');
        $this->assertGuest();
        $member = User::where('email', 'b@b.com')->firstOrFail();
        $member->breezySessions()->create(['two_factor_secret' => 'test-secret']);
        $this->assertFalse(app(AuthenticationMethods::class)->canUseSms($member));
    }

    public function test_disabling_sms_or_rotating_the_key_invalidates_outstanding_challenges(): void
    {
        $this->post('/auth/phone/register', $this->registration());
        $code = $this->code();
        $this->disableSms();
        $this->post('/auth/phone/register/verify', ['code' => $code])->assertForbidden();
        $settings = app(AuthenticationSettings::class);
        $settings->login_methods = ['sms'];
        $settings->registration_methods = ['sms'];
        $settings->smsir_api_key = 'rotated-key';
        $settings->save();
        $this->post('/auth/phone/register/verify', ['code' => $code])->assertSessionHasErrors('code');
        $this->assertGuest();
    }

    public function test_consumed_codes_cannot_be_replayed_and_external_redirects_are_rejected(): void
    {
        $this->get('/auth/phone/register?redirect=https://evil.example/');
        $this->post('/auth/phone/register', $this->registration());
        $id = session('phone_otp.id');
        $nonce = session('phone_otp.nonce');
        $code = $this->code();
        $this->post('/auth/phone/register/verify', ['code' => $code])->assertRedirect('/dashboard');
        $this->post('/logout');
        $this->withSession(['phone_otp' => ['id' => $id, 'nonce' => $nonce]])->post('/auth/phone/register/verify', ['code' => $code])->assertSessionHasErrors('code');
        $this->assertGuest();
    }

    public function test_registration_cannot_reuse_a_reserved_or_deleted_phone_identity(): void
    {
        $member = User::where('email', 'b@b.com')->firstOrFail();
        $identity = UserPhoneIdentity::create(['user_id' => $member->id, 'phone' => '+989123456789', 'verified_at' => now()]);
        $identity->delete();
        $count = User::count();
        $this->post('/auth/phone/register', $this->registration());
        $this->post('/auth/phone/register/verify', ['code' => $this->code()])->assertSessionHasErrors('code');
        $this->assertSame($count, User::count());
        $this->assertGuest();
    }

    public function test_settings_encrypt_the_key_do_not_refill_it_and_reject_lockout_configuration(): void
    {
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $this->actingAs(User::where('email', 'a@a.com')->firstOrFail());
        Livewire::test(ManageAuthentication::class)->assertSet('data.smsir_api_key', '')
            ->fillForm(['login_methods' => ['sms'], 'registration_methods' => ['sms'], 'smsir_api_key' => 'replacement-private-key'])
            ->call('save')->assertHasNoFormErrors()->assertSet('data.smsir_api_key', '');
        $raw = DB::table('settings')->where('group', 'authentication')->where('name', 'smsir_api_key')->value('payload');
        $this->assertStringNotContainsString('replacement-private-key', $raw);
        Livewire::test(ManageAuthentication::class)->fillForm(['smsir_api_key' => ''])->call('save')->assertHasNoFormErrors();
        $this->assertSame('replacement-private-key', app(AuthenticationSettings::class)->smsir_api_key);
        Livewire::test(ManageAuthentication::class)->fillForm(['login_methods' => ['google'], 'registration_methods' => []])->call('save')->assertHasFormErrors(['login_methods']);
        Livewire::test(ManageAuthentication::class)->fillForm(['login_methods' => ['sms'], 'registration_methods' => ['email']])->call('save')->assertHasFormErrors(['registration_methods']);
    }

    public function test_missing_provider_configuration_and_members_cannot_enable_sms(): void
    {
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $settings = app(AuthenticationSettings::class);
        $settings->smsir_api_key = '';
        $settings->smsir_template_id = 0;
        $settings->save();
        $this->actingAs(User::where('email', 'a@a.com')->firstOrFail());
        Livewire::test(ManageAuthentication::class)->fillForm(['login_methods' => ['sms'], 'registration_methods' => []])->call('save')->assertHasFormErrors(['smsir_template_id']);
        $this->actingAs(User::where('email', 'b@b.com')->firstOrFail())->get('/admin/manage-authentication')->assertForbidden();
    }

    public function test_closed_registration_blocks_email_sms_and_social_account_creation(): void
    {
        $settings = app(AuthenticationSettings::class);
        $settings->registration_methods = [];
        $settings->login_methods = ['email', 'sms', 'google'];
        $settings->save();
        $this->post('/register', [])->assertForbidden();
        $this->post('/auth/phone/register', $this->registration())->assertForbidden();
        $oauth = new class
        {
            public function getId(): string
            {
                return 'closed-registration';
            }

            public function getEmail(): string
            {
                return 'closed-registration@example.test';
            }
        };
        try {
            SocialiteUser::resolveUser('google', $oauth);
            $this->fail('Closed registration must not create social accounts.');
        } catch (HttpException $exception) {
            $this->assertSame(403, $exception->getStatusCode());
        }
        $this->assertFalse(User::where('email', 'closed-registration@example.test')->exists());
        Http::assertNothingSent();
    }

    public function test_malformed_code_and_suspended_accounts_cannot_authenticate(): void
    {
        $user = User::where('email', 'b@b.com')->firstOrFail();
        $user->forceFill(['status' => 'suspended'])->save();
        UserPhoneIdentity::create(['user_id' => $user->id, 'phone' => '+989123456789', 'verified_at' => now()]);
        $this->post('/auth/phone/login', ['phone' => '09123456789']);
        $this->post('/auth/phone/login/verify', ['code' => ['123456']])->assertSessionHasErrors('code');
        $this->post('/auth/phone/login/verify', ['code' => $this->code()])->assertSessionHasErrors('code');
        $this->assertGuest();
    }

    public function test_enabled_email_login_and_existing_social_accounts_remain_available_without_new_registration(): void
    {
        $user = User::where('email', 'b@b.com')->firstOrFail();
        $user->updatePassword('existing-email-password');
        $this->post('/login', ['email' => $user->email, 'password' => 'existing-email-password'])->assertRedirect();
        $this->assertAuthenticatedAs($user);
        $this->post('/logout');
        $settings = app(AuthenticationSettings::class);
        $settings->login_methods = ['email', 'google'];
        $settings->registration_methods = [];
        $settings->save();
        SocialiteUser::create(['user_id' => $user->id, 'provider' => 'google', 'provider_id' => 'existing-test-social']);
        $oauth = new class
        {
            public function getId(): string
            {
                return 'existing-test-social';
            }
        };
        $this->assertTrue(SocialiteUser::resolveUser('google', $oauth)->is($user));
        $settings->login_methods = ['email'];
        $settings->save();
        $this->expectException(HttpException::class);
        SocialiteUser::resolveUser('google', $oauth);
    }

    private function registration(): array
    {
        return ['first_name' => 'Test', 'last_name' => 'Person', 'phone' => '09123456789', 'terms' => '1'];
    }

    private function code(): string
    {
        return (string) Http::recorded()->last()[0]['parameters'][0]['value'];
    }

    private function disableSms(): void
    {
        $settings = app(AuthenticationSettings::class);
        $settings->login_methods = ['email'];
        $settings->registration_methods = ['email'];
        $settings->save();
    }
}
