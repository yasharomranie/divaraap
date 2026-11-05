<?php

declare(strict_types=1);

namespace Tests\Feature;

use Filament\Facades\Filament;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Modules\Advertising\Models\AdvertisingInvoice;
use Modules\Advertising\Models\AdvertisingPayment;
use Modules\Advertising\Models\OnlinePaymentAttempt;
use Modules\Site\App\Settings\PaymentGatewaySettings;
use Modules\Site\Filament\Admin\Pages\ManagePaymentGateways;
use Modules\User\App\Models\User;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

final class OnlinePaymentGatewaysTest extends TestCase
{
    private User $admin;

    private User $customer;

    private string $mode = 'success';

    private string $reference = '987654321';

    private int $requests = 0;

    private array $orders = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::where('email', 'a@a.com')->firstOrFail();
        $this->customer = User::factory()->create(['status' => 'active']);
        $settings = app(PaymentGatewaySettings::class);
        $settings->zarinpal_enabled = true;
        $settings->zarinpal_sandbox = false;
        $settings->zarinpal_merchant = '11111111-1111-1111-1111-111111111111';
        $settings->zibal_enabled = true;
        $settings->zibal_sandbox = false;
        $settings->zibal_merchant = 'private-zibal-test-merchant';
        $settings->save();
        Http::preventStrayRequests();
        Http::fake(fn (Request $request) => $this->respond($request));
    }

    public function test_customer_can_choose_either_enabled_gateway_and_amount_comes_from_invoice_balance(): void
    {
        foreach (['zarinpal', 'zibal'] as $gateway) {
            $invoice = $this->invoice();
            $this->get(route('advertising.invoices.show', $invoice))->assertOk()->assertSee('gateway-zarinpal', false)->assertSee('gateway-zibal', false);
            $response = $this->post(route('payments.start', $invoice), ['gateway' => $gateway, 'amount' => 1, 'merchant' => 'attacker']);
            $attempt = OnlinePaymentAttempt::latestForInvoice($invoice->id);
            $this->assertSame($gateway, $attempt->gateway);
            $this->assertSame(1000000, $attempt->amount);
            $this->assertSame('pending', $attempt->status);
            $response->assertRedirectContains($gateway === 'zarinpal' ? 'https://www.zarinpal.com/pg/StartPay/' : 'https://gateway.zibal.ir/start/');
            $this->assertSame('origin', $response->headers->get('Referrer-Policy'));
            Http::assertSent(fn (Request $r): bool => str_contains($r->url(), $gateway === 'zarinpal' ? 'zarinpal.com' : 'zibal.ir') && $r['amount'] === 1000000 && ($gateway === 'zarinpal' ? $r['merchant_id'] === '11111111-1111-1111-1111-111111111111' && $r['currency'] === 'IRR' : $r['merchant'] === 'private-zibal-test-merchant' && $r['orderId'] === $attempt->id));
        }
    }

    public function test_both_gateways_verify_without_login_and_write_one_immutable_receipt(): void
    {
        foreach (['zarinpal', 'zibal'] as $gateway) {
            $this->reference = $gateway === 'zarinpal' ? '123456' : '987654';
            $invoice = $this->invoice();
            $attempt = $this->start($invoice, $gateway);
            Auth::logout();
            $response = $this->get($this->callbackFor($attempt));
            $response->assertOk()->assertSee(__('advertising::gateway.verified'))->assertDontSee($this->customer->email)->assertDontSee($this->customer->name);
            $this->assertStringContainsString('private', $response->headers->get('Cache-Control'));
            $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
            $this->assertSame('no-referrer', $response->headers->get('Referrer-Policy'));
            $this->assertSame('paid', $invoice->fresh()->billingState());
            $payment = $invoice->payments()->sole();
            $this->assertSame($gateway, $payment->gateway);
            $this->assertSame($attempt->id, $payment->online_attempt_id);
            $this->assertSame($this->customer->id, $payment->recorded_by);
            $this->get($this->callbackFor($attempt))->assertOk();
            $this->assertSame(1, $invoice->payments()->count());
            $this->assertSame(1000000, $invoice->fresh()->paidAmount());
            $this->expectImmutable($payment);
        }
    }

    public function test_forged_success_and_failure_fields_do_not_prove_payment(): void
    {
        $invoice = $this->invoice();
        $attempt = $this->start($invoice, 'zibal');
        $this->mode = 'failed';
        $this->get($this->callbackFor($attempt).'&success=1&status=1&amount=1000000&refNumber=444')->assertOk();
        $this->assertSame('failed', $attempt->fresh()->status);
        $this->assertSame(0, $invoice->payments()->count());
        $this->mode = 'success';
        $this->travel(6)->seconds();
        $this->get($this->callbackFor($attempt).'&success=0&status=3')->assertOk();
        $this->assertSame('verified', $attempt->fresh()->status);
        $this->assertSame('paid', $invoice->fresh()->billingState());
    }

    public function test_unknown_or_mismatched_callback_identity_is_rejected_without_contacting_gateway(): void
    {
        $attempt = $this->start($this->invoice(), 'zarinpal');
        $calls = Http::recorded()->count();
        $this->get(str_replace($attempt->callback_token, str_repeat('a', 64), $this->callbackFor($attempt)))->assertNotFound();
        $this->get($attempt->callbackUrl().'?Authority=another')->assertNotFound();
        $this->get($attempt->callbackUrl().'?Authority[]=bad')->assertNotFound();
        $this->assertSame($calls, Http::recorded()->count());
    }

    public function test_duplicate_start_reuses_one_session_and_other_gateway_is_blocked_until_resolved(): void
    {
        $invoice = $this->invoice();
        $attempt = $this->start($invoice, 'zarinpal');
        $this->post(route('payments.start', $invoice), ['gateway' => 'zarinpal'])->assertRedirect();
        $this->post(route('payments.start', $invoice), ['gateway' => 'zibal'])->assertSessionHasErrors('gateway');
        $this->assertSame(1, OnlinePaymentAttempt::where('invoice_id', $invoice->id)->count());
        Http::assertSentCount(1);
        $this->post(route('payments.resume', $attempt))->assertRedirectContains('https://www.zarinpal.com/pg/StartPay/');
        Http::assertSentCount(1);
    }

    public function test_gateway_timeout_does_not_mark_paid_or_trigger_automatic_retry(): void
    {
        $invoice = $this->invoice();
        $attempt = $this->start($invoice, 'zarinpal');
        $this->mode = 'timeout';
        $this->get($this->callbackFor($attempt))->assertOk();
        $this->assertSame('pending', $attempt->fresh()->status);
        $this->assertSame(0, $invoice->payments()->count());
        $this->mode = 'success';
        $this->travel(6)->seconds();
        $this->post(route('payments.check', $attempt))->assertRedirect();
        $this->assertSame('paid', $invoice->fresh()->billingState());
    }

    public function test_zibal_already_verified_uses_inquiry_and_checks_amount_and_order_id(): void
    {
        $invoice = $this->invoice();
        $attempt = $this->start($invoice, 'zibal');
        $this->mode = 'already';
        $this->get($this->callbackFor($attempt))->assertOk();
        Http::assertSent(fn (Request $r): bool => $r->url() === 'https://gateway.zibal.ir/v1/inquiry' && $r['trackId'] === (int) $attempt->authority);
        $this->assertSame('paid', $invoice->fresh()->billingState());
    }

    public function test_mismatched_zibal_verification_is_held_for_review_without_credit(): void
    {
        foreach (['wrong_amount', 'wrong_order', 'wrong_status'] as $mode) {
            $invoice = $this->invoice();
            $attempt = $this->start($invoice, 'zibal');
            $this->mode = $mode;
            $this->get($this->callbackFor($attempt))->assertOk();
            $this->assertSame('needs_review', $attempt->fresh()->status);
            $this->assertSame(0, $invoice->payments()->count());
            $this->post(route('payments.start', $invoice), ['gateway' => 'zarinpal'])->assertSessionHasErrors('gateway');
            $this->mode = 'success';
        }
    }

    public function test_zarinpal_already_verified_response_is_idempotent_and_missing_reference_is_not_success(): void
    {
        $invoice = $this->invoice();
        $attempt = $this->start($invoice, 'zarinpal');
        $this->mode = 'already';
        $this->get($this->callbackFor($attempt))->assertOk();
        $this->assertSame('paid', $invoice->fresh()->billingState());
        $other = $this->invoice();
        $second = $this->start($other, 'zarinpal');
        $this->mode = 'no_reference';
        $this->get($this->callbackFor($second))->assertOk();
        $this->assertSame('pending', $second->fresh()->status);
        $this->assertSame(0, $other->payments()->count());
    }

    public function test_sandbox_payments_never_settle_real_invoices_for_either_gateway(): void
    {
        $settings = app(PaymentGatewaySettings::class);
        $settings->zarinpal_sandbox = true;
        $settings->zibal_sandbox = true;
        $settings->save();
        foreach (['zarinpal', 'zibal'] as $gateway) {
            $invoice = $this->invoice();
            $attempt = $this->start($invoice, $gateway);
            $this->assertTrue($attempt->sandbox);
            if ($gateway === 'zibal') {
                $this->assertSame('zibal', $attempt->merchant);
            }
            $this->get($this->callbackFor($attempt))->assertOk()->assertSee(__('advertising::gateway.test_passed'));
            $this->assertSame('test_passed', $attempt->fresh()->status);
            $this->assertSame(0, $invoice->payments()->count());
            $this->assertSame(1000000, $invoice->fresh()->balance());
        }
    }

    public function test_disabled_gateway_and_unconfigured_or_test_merchant_in_live_mode_cannot_start(): void
    {
        $settings = app(PaymentGatewaySettings::class);
        $settings->zarinpal_enabled = false;
        $settings->zibal_merchant = 'zibal';
        $settings->save();
        $invoice = $this->invoice();
        $this->get(route('advertising.invoices.show', $invoice))->assertOk()->assertDontSee('gateway-zarinpal', false)->assertDontSee('gateway-zibal', false);
        foreach (['zarinpal', 'zibal', 'https://attacker.example'] as $gateway) {
            $this->post(route('payments.start', $invoice), ['gateway' => $gateway])->assertSessionHasErrors('gateway');
        }
        Http::assertNothingSent();
    }

    public function test_partial_manual_receipt_reduces_online_amount_and_pending_blocks_manual_edits(): void
    {
        $invoice = $this->invoice();
        $this->actingAs($this->admin);
        AdvertisingPayment::recordReceived($invoice, '400000', 'MANUAL-PARTIAL');
        $this->actingAs($this->customer);
        $attempt = $this->start($invoice, 'zibal');
        $this->assertSame(600000, $attempt->amount);
        $this->actingAs($this->admin);
        try {
            AdvertisingPayment::recordReceived($invoice, '600000', 'MANUAL-DURING-ONLINE');
            $this->fail('Unresolved online payment must reserve the invoice.');
        } catch (ValidationException) {
            $this->assertSame(1, $invoice->payments()->count());
        }
        $this->get($this->callbackFor($attempt))->assertOk();
        $this->assertSame('paid', $invoice->fresh()->billingState());
        $this->assertSame(2, $invoice->payments()->count());
    }

    public function test_pending_invoice_cannot_be_voided_and_paid_invoice_cannot_be_paid_again(): void
    {
        $invoice = $this->invoice();
        $attempt = $this->start($invoice, 'zibal');
        $this->actingAs($this->admin);
        try {
            $invoice->voidInvoice('Should be held');
            $this->fail('Unresolved payment must prevent voiding.');
        } catch (ValidationException) {
            $this->assertNull($invoice->fresh()->voided_at);
        }
        $this->get($this->callbackFor($attempt));
        $this->actingAs($this->customer)->post(route('payments.start', $invoice), ['gateway' => 'zibal'])->assertSessionHasErrors('gateway');
    }

    public function test_late_distinct_payment_is_recorded_truthfully_and_excess_is_visible(): void
    {
        $invoice = $this->invoice();
        $old = $this->start($invoice, 'zibal');
        $this->mode = 'failed';
        $this->get($this->callbackFor($old));
        $this->mode = 'success';
        $new = $this->start($invoice, 'zarinpal');
        $this->reference = '11111';
        $this->get($this->callbackFor($new));
        $this->travel(6)->seconds();
        $this->reference = '22222';
        $this->get($this->callbackFor($old));
        $this->assertSame(2000000, $invoice->fresh()->paidAmount());
        $this->assertSame(1000000, $invoice->fresh()->unallocatedAmount());
        $this->assertSame(0, $invoice->fresh()->balance());
        $this->get(route('advertising.invoices.show', $invoice))->assertOk()->assertSee(__('advertising::gateway.overpayment_help'));
    }

    public function test_provider_reference_cannot_be_credited_to_two_invoices(): void
    {
        $invoice = $this->invoice();
        $first = $this->start($invoice, 'zibal');
        $this->get($this->callbackFor($first));
        $other = $this->invoice();
        $second = $this->start($other, 'zibal');
        $this->get($this->callbackFor($second));
        $this->assertSame('needs_review', $second->fresh()->status);
        $this->assertSame(0, $other->payments()->count());
        $this->assertSame(1, $invoice->payments()->count());
    }

    public function test_merchant_rotation_and_disabling_does_not_break_reconciliation_of_existing_payment(): void
    {
        $invoice = $this->invoice();
        $attempt = $this->start($invoice, 'zibal');
        $settings = app(PaymentGatewaySettings::class);
        $settings->zibal_merchant = 'replacement-live-merchant';
        $settings->zibal_enabled = false;
        $settings->save();
        $this->get($this->callbackFor($attempt));
        Http::assertSent(fn (Request $r): bool => str_ends_with($r->url(), '/verify') && $r['merchant'] === 'private-zibal-test-merchant');
        $this->assertSame('paid', $invoice->fresh()->billingState());
    }

    public function test_another_user_guest_or_suspended_user_cannot_start_resume_or_check_payment(): void
    {
        $invoice = $this->invoice();
        $attempt = $this->start($invoice, 'zibal');
        $other = User::factory()->create(['status' => 'active']);
        $this->actingAs($other)->post(route('payments.start', $invoice), ['gateway' => 'zibal'])->assertNotFound();
        $this->post(route('payments.resume', $attempt))->assertNotFound();
        $this->post(route('payments.check', $attempt))->assertNotFound();
        $other->forceFill(['status' => 'suspended'])->save();
        $this->post(route('payments.check', $attempt))->assertForbidden();
        Auth::logout();
        $this->post(route('payments.start', $invoice), ['gateway' => 'zibal'])->assertRedirectContains('/login');
        Http::assertSentCount(1);
    }

    public function test_private_credentials_are_encrypted_and_not_serialized_or_sent_to_admin_form(): void
    {
        $attempt = $this->start($this->invoice(), 'zibal');
        $raw = DB::table('online_payment_attempts')->where('id', $attempt->id)->first();
        $this->assertStringNotContainsString('private-zibal-test-merchant', $raw->merchant);
        $this->assertStringNotContainsString($attempt->callback_token, $raw->callback_token);
        foreach (['merchant', 'authority', 'callback_token', 'callback_hash'] as $key) {
            $this->assertArrayNotHasKey($key, $attempt->toArray());
        }
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $this->actingAs($this->admin);
        Livewire::test(ManagePaymentGateways::class)->assertSet('data.zibal_merchant', '')->assertDontSee('private-zibal-test-merchant')
            ->fillForm(['zibal_merchant' => 'new-private-merchant'])->call('save')->assertHasNoFormErrors()->assertSet('data.zibal_merchant', '');
        Livewire::test(ManagePaymentGateways::class)->fillForm(['zibal_merchant' => ''])->call('save')->assertHasNoFormErrors();
        $this->assertSame('new-private-merchant', app(PaymentGatewaySettings::class)->zibal_merchant);
        $payload = DB::table('settings')->where('group', 'payment_gateways')->where('name', 'zibal_merchant')->value('payload');
        $this->assertStringNotContainsString('new-private-merchant', $payload);
    }

    public function test_settings_reject_invalid_live_credentials_and_staff_cannot_access_payment_management(): void
    {
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $this->actingAs($this->admin);
        Livewire::test(ManagePaymentGateways::class)->fillForm(['zibal_merchant' => 'zibal', 'zibal_sandbox' => false])->call('save')->assertHasFormErrors(['zibal_merchant']);
        Livewire::test(ManagePaymentGateways::class)->fillForm(['zarinpal_merchant' => 'invalid'])->call('save')->assertHasFormErrors(['zarinpal_merchant']);
        $this->get('/admin/online-payment-attempts')->assertOk();
        $this->actingAs($this->customer)->get('/admin/manage-payment-gateways')->assertForbidden();
        $this->get('/admin/online-payment-attempts')->assertForbidden();
    }

    public function test_callback_uses_configured_origin_and_never_the_request_host(): void
    {
        config(['app.url' => 'https://shop.example.test']);
        $invoice = $this->invoice();
        $this->withHeader('Host', 'attacker.example')->post(route('payments.start', $invoice), ['gateway' => 'zibal'])->assertRedirect();
        Http::assertSent(fn (Request $r): bool => str_starts_with($r['callbackUrl'] ?? '', 'https://shop.example.test/payments/return/'));
    }

    public function test_unsupported_amounts_void_invoices_and_mutating_models_directly_are_rejected(): void
    {
        foreach (['999', '4000000001'] as $amount) {
            $invoice = $this->invoice($amount);
            $this->post(route('payments.start', $invoice), ['gateway' => 'zibal'])->assertSessionHasErrors('gateway');
        }
        $invoice = $this->invoice();
        $this->actingAs($this->admin);
        $invoice->voidInvoice('Cancelled');
        $this->actingAs($this->customer)->post(route('payments.start', $invoice), ['gateway' => 'zibal'])->assertSessionHasErrors('gateway');
        Http::assertNothingSent();
        $this->expectException(HttpException::class);
        OnlinePaymentAttempt::create(['status' => 'verified']);
    }

    public function test_callback_check_is_rate_limited_and_bank_secrets_are_not_recorded_in_activity(): void
    {
        $attempt = $this->start($this->invoice(), 'zibal');
        $this->get($this->callbackFor($attempt));
        $this->get($this->callbackFor($attempt));
        Http::assertSentCount(2);
        $activity = DB::table('activity_log')->where('subject_type', AdvertisingInvoice::class)->where('subject_id', $attempt->invoice_id)->get()->toJson();
        $this->assertStringNotContainsString('private-zibal-test-merchant', $activity);
        $this->assertStringNotContainsString($attempt->callback_token, $activity);
        $this->assertStringNotContainsString('6274', $activity);
    }

    public function test_request_failure_timeout_and_redirect_responses_do_not_create_payable_sessions(): void
    {
        foreach (['request_failed', 'request_timeout', 'request_redirect'] as $mode) {
            $this->mode = $mode;
            $invoice = $this->invoice();
            $this->post(route('payments.start', $invoice), ['gateway' => 'zibal'])->assertSessionHasErrors('gateway');
            $attempt = OnlinePaymentAttempt::latestForInvoice($invoice->id);
            $this->assertSame('failed', $attempt->status);
            $this->assertNull($attempt->authority);
            $this->assertSame(0, $invoice->payments()->count());
        }
        $this->assertSame(3, $this->requests);
    }

    public function test_duplicate_authority_response_is_held_without_reassigning_a_bank_session(): void
    {
        $first = $this->start($this->invoice(), 'zibal');
        $this->requests--;
        $invoice = $this->invoice();
        $this->post(route('payments.start', $invoice), ['gateway' => 'zibal'])->assertSessionHasErrors('gateway');
        $second = OnlinePaymentAttempt::latestForInvoice($invoice->id);
        $this->assertSame('needs_review', $second->status);
        $this->assertNull($second->authority);
        $this->assertSame('pending', $first->fresh()->status);
    }

    public function test_late_verified_payment_after_invoice_void_is_retained_for_reconciliation(): void
    {
        $invoice = $this->invoice();
        $attempt = $this->start($invoice, 'zibal');
        $this->mode = 'failed';
        $this->get($this->callbackFor($attempt));
        $this->actingAs($this->admin);
        $invoice->fresh()->voidInvoice('Provider initially reported unpaid');
        $this->travel(6)->seconds();
        $this->mode = 'success';
        $this->get($this->callbackFor($attempt))->assertOk();
        $this->assertSame('void', $invoice->fresh()->billingState());
        $this->assertSame(1000000, $invoice->fresh()->unallocatedAmount());
        $this->assertSame(1, $invoice->payments()->count());
    }

    private function invoice(string $amount = '1000000'): AdvertisingInvoice
    {
        $this->actingAs($this->admin);
        $invoice = AdvertisingInvoice::create(['user_id' => $this->customer->id, 'description' => 'Gateway test invoice', 'amount' => $amount]);
        $this->actingAs($this->customer);

        return $invoice;
    }

    private function start(AdvertisingInvoice $invoice, string $gateway): OnlinePaymentAttempt
    {
        $this->post(route('payments.start', $invoice), ['gateway' => $gateway])->assertRedirect();

        return OnlinePaymentAttempt::latestForInvoice($invoice->id);
    }

    private function callbackFor(OnlinePaymentAttempt $attempt): string
    {
        return $attempt->callbackUrl().'?'.http_build_query([$attempt->gateway === 'zarinpal' ? 'Authority' : 'trackId' => $attempt->authority]);
    }

    private function expectImmutable(AdvertisingPayment $payment): void
    {
        try {
            $payment->amount = 1;
            $payment->save();
            $this->fail('Gateway receipt must be immutable.');
        } catch (ValidationException) {
            $this->assertSame(1000000, $payment->fresh()->amount);
        }
    }

    private function respond(Request $request): mixed
    {
        $zarinpal = str_contains($request->url(), 'zarinpal.com');
        if (str_contains($request->url(), 'request')) {
            $this->requests++;
            if ($this->mode === 'request_timeout') {
                return Http::failedConnection()($request);
            }
            if ($this->mode === 'request_failed') {
                return Http::response(['result' => 104]);
            }
            if ($this->mode === 'request_redirect') {
                return Http::response('', 302, ['Location' => 'https://attacker.example']);
            }
            $authority = $zarinpal ? 'A'.str_pad((string) $this->requests, 35, '0', STR_PAD_LEFT) : (string) (1000 + $this->requests);
            $this->orders[$authority] = $request->data();

            return Http::response($zarinpal ? ['data' => ['code' => 100, 'authority' => $authority]] : ['result' => 100, 'trackId' => (int) $authority]);
        }
        if ($this->mode === 'timeout') {
            return Http::failedConnection()($request);
        }
        $authority = (string) ($zarinpal ? $request['authority'] : $request['trackId']);
        if ($this->mode === 'failed') {
            return Http::response($zarinpal ? ['errors' => ['code' => -51]] : ['result' => 202]);
        }
        if ($zarinpal) {
            return Http::response(['data' => ['code' => $this->mode === 'already' ? 101 : 100, 'ref_id' => $this->mode === 'no_reference' ? null : (int) $this->reference]]);
        }
        if ($this->mode === 'already' && str_ends_with($request->url(), '/verify')) {
            return Http::response(['result' => 201]);
        }
        $order = $this->orders[$authority];

        return Http::response(['result' => 100, 'status' => $this->mode === 'wrong_status' ? 2 : 1, 'amount' => $this->mode === 'wrong_amount' ? 1 : $order['amount'], 'orderId' => $this->mode === 'wrong_order' ? 'another-order' : $order['orderId'], 'refNumber' => (int) $this->reference, 'cardNumber' => '6274****1111']);
    }
}
