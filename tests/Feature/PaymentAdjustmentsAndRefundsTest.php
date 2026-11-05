<?php

declare(strict_types=1);

namespace Tests\Feature;

use Filament\Facades\Filament;
use Illuminate\Http\Client\Factory;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Modules\Advertising\Filament\Admin\Resources\AdvertisingRefundRequestResource\Pages\ListAdvertisingRefundRequests;
use Modules\Advertising\Models\AdvertisingInvoice;
use Modules\Advertising\Models\AdvertisingPayment;
use Modules\Advertising\Models\AdvertisingPaymentAdjustment;
use Modules\Advertising\Models\AdvertisingRefundRequest;
use Modules\Advertising\Models\OnlinePaymentAttempt;
use Modules\Promotion\Models\PromotionPlan;
use Modules\Promotion\Models\SellerPackage;
use Modules\Promotion\Models\SellerPackagePurchase;
use Modules\Site\App\Settings\PaymentGatewaySettings;
use Modules\User\App\Models\User;
use Spatie\Activitylog\Models\Activity;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

final class PaymentAdjustmentsAndRefundsTest extends TestCase
{
    private User $admin;

    private User $customer;

    private AdvertisingInvoice $invoice;

    private AdvertisingPayment $payment;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::where('email', 'a@a.com')->firstOrFail();
        $this->customer = User::factory()->create(['status' => 'active']);
        $this->actingAs($this->admin);
        $this->invoice = AdvertisingInvoice::create(['user_id' => $this->customer->id, 'description' => 'Refund fixture', 'amount' => '1000000']);
        $this->payment = AdvertisingPayment::recordReceived($this->invoice, '1000000', 'REFUND-ORIGINAL');
        Http::preventStrayRequests();
        Http::fake();
        Filament::setCurrentPanel(Filament::getPanel('admin'));
    }

    public function test_manual_correction_is_append_only_and_updates_valid_receipts_without_erasing_the_original(): void
    {
        $key = (string) Str::uuid();
        $entry = AdvertisingPaymentAdjustment::correct($this->payment, '200000', 'PRIVATE CORRECTION REASON', $key);
        $invoice = $this->invoice->fresh();
        $this->assertSame(1000000, $invoice->grossReceivedAmount());
        $this->assertSame(200000, $invoice->correctedAmount());
        $this->assertSame(800000, $invoice->paidAmount());
        $this->assertSame(800000, $invoice->netReceivedAmount());
        $this->assertSame(200000, $invoice->balance());
        $this->assertSame('partial', $invoice->billingState());
        $this->assertSame(1000000, $this->payment->fresh()->amount);
        $this->assertSame(1, $invoice->payments()->count());
        $this->assertSame($entry->id, AdvertisingPaymentAdjustment::correct($this->payment, '200000', 'PRIVATE CORRECTION REASON', $key)->id);
        $this->invalid(fn () => AdvertisingPaymentAdjustment::correct($this->payment, '300000', 'PRIVATE CORRECTION REASON', $key), 'operation_key');
        $this->assertSame(1, $invoice->adjustments()->count());
        $this->assertStringNotContainsString('PRIVATE CORRECTION REASON', DB::table('advertising_payment_adjustments')->where('id', $entry->id)->value('reason'));
        $this->assertArrayNotHasKey('reason', $entry->toArray());
        $this->assertStringNotContainsString('PRIVATE CORRECTION REASON', Activity::where('subject_type', AdvertisingPaymentAdjustment::class)->where('subject_id', $entry->id)->get()->toJson());
    }

    public function test_invalid_amounts_reasons_and_overcorrection_are_rejected(): void
    {
        foreach (['0', '-1', '1.1', '1e3', '1000001', '1000000000001'] as $amount) {
            $this->invalid(fn () => AdvertisingPaymentAdjustment::correct($this->payment, $amount, 'Reason', (string) Str::uuid()), 'amount');
        }
        $this->invalid(fn () => AdvertisingPaymentAdjustment::correct($this->payment, '1', ' ', (string) Str::uuid()), 'reason');
        $this->invalid(fn () => AdvertisingPaymentAdjustment::correct($this->payment, '1', 'Reason', 'bad-key'), 'operation_key');
        AdvertisingPaymentAdjustment::correct($this->payment, '600000', 'Correction', (string) Str::uuid());
        $this->invalid(fn () => AdvertisingPaymentAdjustment::correct($this->payment, '500000', 'Correction', (string) Str::uuid()), 'amount');
        $this->assertSame(400000, AdvertisingPaymentAdjustment::availableForPayment($this->payment));
    }

    public function test_customer_request_reserves_funds_and_duplicate_form_cannot_reopen_a_withdrawn_request(): void
    {
        $this->actingAs($this->customer);
        $key = (string) Str::uuid();
        $request = AdvertisingRefundRequest::requestFor($this->payment, '400000', 'Return request', $key);
        $this->assertSame('requested', $request->state);
        $this->assertSame($this->customer->id, $request->user_id);
        $this->assertSame(400000, $this->invoice->fresh()->reservedRefundAmount());
        $this->assertSame(600000, AdvertisingPaymentAdjustment::availableForPayment($this->payment));
        $this->assertFalse($this->invoice->fresh()->collectionOpen());
        $this->assertSame('refund_pending', $this->invoice->fresh()->billingState());
        $this->assertSame($request->id, AdvertisingRefundRequest::requestFor($this->payment, '400000', 'Return request', $key)->id);
        $this->invalid(fn () => AdvertisingRefundRequest::requestFor($this->payment, '1', 'Another', (string) Str::uuid()), 'amount');
        $request->withdraw(0);
        $this->assertSame('withdrawn', AdvertisingRefundRequest::requestFor($this->payment, '400000', 'Return request', $key)->state);
        $this->assertTrue($this->invoice->fresh()->collectionOpen());
        $this->assertSame('paid', $this->invoice->fresh()->billingState());
        $this->assertSame(0, $this->invoice->adjustments()->count());
    }

    public function test_approval_and_confirmed_external_refund_produce_one_immutable_negative_entry(): void
    {
        $request = $this->customerRequest('300000');
        $this->actingAs($this->admin);
        $this->invalid(fn () => $request->complete('BANK-OUT-001', 0, true), 'refund');
        $approved = $request->decide('approve', 0);
        $this->invalid(fn () => $approved->complete('BANK-OUT-001', 1, false), 'reference');
        $completed = $approved->complete('bank-out-001', 1, true);
        $this->assertSame('completed', $completed->state);
        $this->assertSame(2, $completed->version);
        $this->assertSame('completed', $request->complete('BANK-OUT-001', 0, true)->state);
        $this->invalid(fn () => $request->complete('BANK-OUT-002', 2, true), 'reference');
        $invoice = $this->invoice->fresh();
        $this->assertSame(1000000, $invoice->paidAmount());
        $this->assertSame(300000, $invoice->refundedAmount());
        $this->assertSame(700000, $invoice->netReceivedAmount());
        $this->assertSame(0, $invoice->reservedRefundAmount());
        $this->assertSame(0, $invoice->balance());
        $this->assertSame('partially_refunded', $invoice->billingState());
        $this->assertFalse($invoice->collectionOpen());
        $entry = $invoice->adjustments()->sole();
        $this->assertSame('BANK-OUT-001', $entry->reference);
        $this->assertSame($request->id, $entry->refund_request_id);
        $this->invalid(fn () => $entry->update(['amount' => 1]), 'adjustment');
        $this->invalid(fn () => $entry->delete(), 'adjustment');
        Http::assertNothingSent();
    }

    public function test_full_refund_and_combined_correction_never_create_negative_available_funds(): void
    {
        AdvertisingPaymentAdjustment::correct($this->payment, '200000', 'Wrong original amount', (string) Str::uuid());
        $request = $this->customerRequest('800000');
        $this->actingAs($this->admin);
        $request->decide('approve', 0)->complete('REFUND-ALL', 1, true);
        $invoice = $this->invoice->fresh();
        $this->assertSame('refunded', $invoice->billingState());
        $this->assertSame(0, $invoice->netReceivedAmount());
        $this->assertSame(0, AdvertisingPaymentAdjustment::availableForPayment($this->payment));
        $this->invalid(fn () => AdvertisingPaymentAdjustment::correct($this->payment, '1', 'More', (string) Str::uuid()), 'amount');
        $this->invalid(fn () => AdvertisingRefundRequest::requestFor($this->payment, '1', 'More', (string) Str::uuid()), 'amount');
    }

    public function test_rejection_and_cancelled_approval_release_reservations_only_without_transfer(): void
    {
        $request = $this->customerRequest('200000');
        $this->actingAs($this->admin);
        $rejected = $request->decide('reject', 0, 'Service already delivered');
        $this->assertSame('rejected', $rejected->state);
        $this->assertSame(1000000, AdvertisingPaymentAdjustment::availableForPayment($this->payment));
        $next = $this->customerRequest('200000');
        $this->actingAs($this->admin);
        $approved = $next->decide('approve', 0);
        $this->invalid(fn () => $approved->decide('cancel', 1, 'No transfer'), 'refund');
        $cancelled = $approved->decide('cancel', 1, 'No money transferred', true);
        $this->assertSame('cancelled', $cancelled->state);
        $this->assertSame(0, $this->invoice->fresh()->reservedRefundAmount());
        $this->assertSame(0, $this->invoice->adjustments()->count());
        $this->assertTrue($this->invoice->fresh()->collectionOpen());
    }

    public function test_stale_decisions_and_withdrawal_after_approval_are_rejected(): void
    {
        $request = $this->customerRequest();
        $this->actingAs($this->admin);
        $request->decide('approve', 0);
        $this->invalid(fn () => $request->decide('reject', 0, 'Too late'), 'refund');
        $this->actingAs($this->customer);
        $this->invalid(fn () => $request->withdraw(0), 'refund');
        $this->invalid(fn () => $request->withdraw(1), 'refund');
        $this->assertSame('approved', $request->fresh()->state);
    }

    public function test_source_budget_is_shared_by_pending_refunds_and_corrections(): void
    {
        $request = $this->customerRequest('600000');
        $this->actingAs($this->admin);
        AdvertisingPaymentAdjustment::correct($this->payment, '400000', 'Only unreserved funds', (string) Str::uuid());
        $this->invalid(fn () => AdvertisingPaymentAdjustment::correct($this->payment, '1', 'Too much', (string) Str::uuid()), 'amount');
        $request->decide('approve', 0)->complete('BUDGET-REFUND', 1, true);
        $this->assertSame(0, $this->invoice->fresh()->netReceivedAmount());
        $this->assertSame(0, AdvertisingPaymentAdjustment::availableForPayment($this->payment));
    }

    public function test_two_refunds_cannot_share_the_same_outgoing_bank_reference(): void
    {
        $first = $this->customerRequest('200000');
        $this->actingAs($this->admin);
        $first->decide('approve', 0)->complete('OUT-SAME', 1, true);
        $second = $this->customerRequest('200000');
        $this->actingAs($this->admin);
        $approved = $second->decide('approve', 0);
        $this->invalid(fn () => $approved->complete('out-same', 1, true), 'reference');
        $this->assertSame('approved', $second->fresh()->state);
        $this->assertSame(200000, $this->invoice->fresh()->refundedAmount());
    }

    public function test_partial_invoice_does_not_collect_more_during_or_after_refund(): void
    {
        AdvertisingPaymentAdjustment::correct($this->payment, '600000', 'Only 400000 was received', (string) Str::uuid());
        $request = $this->customerRequest('100000');
        $this->actingAs($this->admin);
        $this->invalid(fn () => AdvertisingPayment::recordReceived($this->invoice, '1000', 'DURING-REFUND'), 'amount');
        $request->decide('approve', 0)->complete('PARTIAL-REFUND', 1, true);
        $this->invalid(fn () => AdvertisingPayment::recordReceived($this->invoice, '1000', 'AFTER-REFUND'), 'amount');
        $this->assertSame(0, $this->invoice->fresh()->balance());
        $this->assertSame(300000, $this->invoice->fresh()->netReceivedAmount());
    }

    public function test_native_refund_form_accepts_persian_amounts_and_ignores_spoofed_customer_or_state(): void
    {
        $this->actingAs($this->customer);
        $url = route('refunds.store', [$this->invoice, $this->payment]);
        $this->get($url)->assertOk()->assertSee('refund-amount', false);
        $this->post($url, ['amount' => '۲۰۰۰۰۰', 'reason' => 'مبلغ اضافه', 'form_key' => (string) Str::uuid(), 'user_id' => $this->admin->id, 'state' => 'completed'])->assertRedirect(route('advertising.invoices.show', $this->invoice));
        $request = AdvertisingRefundRequest::sole();
        $this->assertSame(200000, $request->amount);
        $this->assertSame($this->customer->id, $request->user_id);
        $this->assertSame('requested', $request->state);
        $this->get(route('advertising.invoices.show', $this->invoice))->assertOk()->assertSee(__('advertising::refunds.collection_hold'));
        $this->post(route('refunds.withdraw', $request), ['version' => 0])->assertRedirect();
        $this->assertSame('withdrawn', $request->fresh()->state);
    }

    public function test_other_accounts_staff_and_inactive_accounts_cannot_modify_or_read_private_finances(): void
    {
        $request = $this->customerRequest();
        $other = User::factory()->create(['status' => 'active']);
        $this->actingAs($other)->get(route('refunds.create', [$this->invoice, $this->payment]))->assertNotFound();
        $this->post(route('refunds.withdraw', $request), ['version' => 0])->assertNotFound();
        $this->post(route('refunds.correct', [$this->invoice, $this->payment]), ['amount' => '1', 'reason' => 'No', 'operation_key' => (string) Str::uuid(), 'confirmed' => 1])->assertForbidden();
        $this->get('/admin/advertising-refund-requests')->assertForbidden();
        $this->actingAs($this->customer);
        try {
            $request->complete('FAKE-REFUND', 0, true);
            $this->fail('Customer cannot complete refunds.');
        } catch (HttpException $exception) {
            $this->assertSame(403, $exception->getStatusCode());
        }
        $this->customer->forceFill(['status' => 'suspended'])->save();
        $this->get(route('refunds.create', [$this->invoice, $this->payment]))->assertForbidden();
    }

    public function test_correction_post_requires_explicit_confirmation_and_preserves_input_on_failure(): void
    {
        $url = route('refunds.correct', [$this->invoice, $this->payment]);
        $data = ['amount' => '100000', 'reason' => 'Actual receipt was lower', 'operation_key' => (string) Str::uuid(), 'correction_payment_id' => $this->payment->id];
        $this->from(route('advertising.invoices.show', $this->invoice))->post($url, $data)->assertSessionHasErrors('confirmed');
        $this->assertSame($data['reason'], session()->getOldInput('reason'));
        $this->post($url, $data + ['confirmed' => 1])->assertRedirect();
        $this->assertSame(900000, $this->invoice->fresh()->paidAmount());
    }

    public function test_sensitive_reasons_are_encrypted_and_not_written_to_activity_logs_or_serialization(): void
    {
        $request = $this->customerRequest('100000', 'PRIVATE CUSTOMER REASON');
        $this->actingAs($this->admin);
        $request->decide('reject', 0, 'PRIVATE MANAGER DECISION');
        $raw = DB::table('advertising_refund_requests')->where('id', $request->id)->first();
        $this->assertStringNotContainsString('PRIVATE CUSTOMER REASON', $raw->reason);
        $this->assertStringNotContainsString('PRIVATE MANAGER DECISION', $raw->decision_reason);
        $this->assertArrayNotHasKey('reason', $request->toArray());
        $this->assertArrayNotHasKey('form_key', $request->toArray());
        $activity = Activity::where('subject_type', AdvertisingRefundRequest::class)->where('subject_id', $request->id)->get()->toJson();
        $this->assertStringNotContainsString('PRIVATE CUSTOMER REASON', $activity);
        $this->assertStringNotContainsString('PRIVATE MANAGER DECISION', $activity);
        $this->assertStringContainsString('reviewed_by', $activity);
    }

    public function test_admin_resource_checks_confirmation_and_versions_before_recording_refunds(): void
    {
        $request = $this->customerRequest();
        $this->actingAs($this->admin);
        Livewire::test(ListAdvertisingRefundRequests::class)->callTableAction('approve', $request, ['version' => 0])->assertHasNoTableActionErrors();
        $this->assertSame('approved', $request->fresh()->state);
        Livewire::test(ListAdvertisingRefundRequests::class)->callTableAction('complete', $request->fresh(), ['version' => 1, 'reference' => 'ADMIN-OUT-001', 'confirmed' => false])->assertHasTableActionErrors(['confirmed']);
        Livewire::test(ListAdvertisingRefundRequests::class)->callTableAction('complete', $request->fresh(), ['version' => 1, 'reference' => 'ADMIN-OUT-001', 'confirmed' => true])->assertHasNoTableActionErrors();
        $this->assertSame('completed', $request->fresh()->state);
        $this->assertSame(1, AdvertisingPaymentAdjustment::where('refund_request_id', $request->id)->count());
    }

    public function test_escaped_history_and_aggregated_statement_match_raw_financial_totals(): void
    {
        AdvertisingPaymentAdjustment::correct($this->payment, '100000', '<script>privateCorrection()</script>', (string) Str::uuid());
        $request = $this->customerRequest('200000', '<script>privateRefund()</script>');
        $this->actingAs($this->admin);
        $request->decide('approve', 0)->complete('STATEMENT-OUT', 1, true);
        $this->actingAs($this->customer);
        $response = $this->get(route('advertising.invoices.show', $this->invoice));
        $response->assertOk()->assertDontSee('<script>privateRefund()</script>', false)->assertDontSee('<script>privateCorrection()</script>', false)->assertSee('&lt;script&gt;privateRefund()', false);
        $snapshot = AdvertisingInvoice::financialQuery()->findOrFail($this->invoice->id);
        $this->assertSame(100000, $snapshot->correctedAmount());
        $this->assertSame(200000, $snapshot->refundedAmount());
        $this->assertSame(700000, $snapshot->netReceivedAmount());
        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
    }

    public function test_package_credits_pause_after_reducing_principal_and_resume_after_rejected_request(): void
    {
        $this->actingAs($this->admin);
        $plan = PromotionPlan::create(['name' => 'Refund package plan', 'slug' => (string) Str::uuid(), 'price' => 100, 'currency' => 'IRR', 'duration_days' => 3, 'is_active' => true]);
        $package = SellerPackage::create(['name' => 'Refund package', 'promotion_plan_id' => $plan->id, 'price' => 1000000, 'credits' => 2, 'validity_days' => 30, 'is_active' => true]);
        $this->actingAs($this->customer);
        $purchase = SellerPackagePurchase::requestPackage($package->id, SellerPackage::version($package->offer()));
        $this->actingAs($this->admin);
        $purchase->review('invoice');
        $receipt = AdvertisingPayment::recordReceived($purchase->invoice, '1000000', 'REFUND-PACKAGE');
        $purchase->review('activate');
        $this->assertSame('active', $purchase->displayState());
        $this->actingAs($this->customer);
        $request = AdvertisingRefundRequest::requestFor($receipt, '100000', 'Request review', (string) Str::uuid());
        $this->assertSame('financial_hold', $purchase->fresh()->displayState());
        $this->actingAs($this->admin);
        $request->decide('reject', 0, 'No refund accepted');
        $this->assertSame('active', $purchase->fresh()->displayState());
        AdvertisingPaymentAdjustment::correct($receipt, '100000', 'Recorded too high', (string) Str::uuid());
        $this->assertSame('financial_hold', $purchase->fresh()->displayState());
        $this->assertSame(0, $purchase->fresh()->used_credits);
        $this->actingAs($this->customer);
        $remaining = AdvertisingRefundRequest::requestFor($receipt, '900000', 'Close remaining contract', (string) Str::uuid());
        $this->actingAs($this->admin);
        $remaining->decide('approve', 0)->complete('PACKAGE-REMAINING-REFUND', 1, true);
        $this->actingAs($this->customer);
        $new = SellerPackagePurchase::requestPackage($package->id, SellerPackage::version($package->offer()));
        $this->assertNotSame($purchase->id, $new->id);
        $this->assertSame('requested', $new->state);
    }

    public function test_ledger_and_request_records_cannot_be_created_updated_or_removed_directly(): void
    {
        $request = $this->customerRequest();
        $this->invalid(fn () => $request->update(['state' => 'completed']), 'refund');
        $this->invalid(fn () => $request->delete(), 'refund');
        $this->invalid(fn () => AdvertisingPaymentAdjustment::create(['kind' => 'refund', 'amount' => 1]), 'adjustment');
    }

    public function test_verified_gateway_receipts_can_be_refunded_but_never_manually_corrected(): void
    {
        $invoice = $this->unpaidInvoice();
        $paid = true;
        $attempt = $this->gatewayAttempt($invoice, $paid);
        $attempt->check();
        $source = $invoice->payments()->sole();
        $this->actingAs($this->admin);
        $this->invalid(fn () => AdvertisingPaymentAdjustment::correct($source, '1', 'Cannot rewrite bank verification', (string) Str::uuid()), 'amount');
        $this->actingAs($this->customer);
        $request = AdvertisingRefundRequest::requestFor($source, '200000', 'Return part', (string) Str::uuid());
        $this->actingAs($this->admin);
        $request->decide('approve', 0)->complete('BANK-GATEWAY-REFUND', 1, true);
        $attempt->check();
        $this->assertSame(1, $invoice->payments()->count());
        $this->assertSame(800000, $invoice->fresh()->netReceivedAmount());
        Http::assertSentCount(2);
    }

    public function test_refunding_only_excess_does_not_make_the_original_service_unpaid(): void
    {
        $invoice = $this->unpaidInvoice();
        $paid = false;
        $attempt = $this->gatewayAttempt($invoice, $paid);
        $attempt->check();
        $this->actingAs($this->admin);
        AdvertisingPayment::recordReceived($invoice, '1000000', 'OTHER-INCOMING');
        $this->travel(6)->seconds();
        $paid = true;
        $attempt->check();
        $source = $invoice->payments()->where('gateway', 'zibal')->sole();
        $this->assertSame(1000000, $invoice->fresh()->unallocatedAmount());
        $this->actingAs($this->customer);
        $request = AdvertisingRefundRequest::requestFor($source, '1000000', 'Return excess only', (string) Str::uuid());
        $this->assertSame('paid', $invoice->fresh()->billingState());
        $this->actingAs($this->admin);
        $request->decide('approve', 0)->complete('EXCESS-OUTGOING', 1, true);
        $this->assertSame('paid', $invoice->fresh()->billingState());
        $this->assertSame(1000000, $invoice->fresh()->netReceivedAmount());
        $this->assertSame(0, $invoice->fresh()->unallocatedAmount());
    }

    public function test_late_money_on_a_void_invoice_can_be_reconciled_with_a_refund(): void
    {
        $invoice = $this->unpaidInvoice();
        $paid = false;
        $attempt = $this->gatewayAttempt($invoice, $paid);
        $attempt->check();
        $this->actingAs($this->admin);
        $invoice->voidInvoice('Initially unpaid');
        $this->travel(6)->seconds();
        $paid = true;
        $attempt->check();
        $source = $invoice->payments()->sole();
        $request = AdvertisingRefundRequest::requestFor($source, '1000000', 'Return late bank receipt', (string) Str::uuid());
        $request->decide('approve', 0)->complete('VOID-REFUND-OUT', 1, true);
        $this->assertSame('void', $invoice->fresh()->billingState());
        $this->assertSame(0, $invoice->fresh()->netReceivedAmount());
        $this->assertSame(0, $invoice->fresh()->unallocatedAmount());
    }

    public function test_upward_correction_of_an_understated_manual_receipt_is_linked_and_refundable(): void
    {
        $invoice = $this->unpaidInvoice();
        $payment = AdvertisingPayment::recordReceived($invoice, '500000', 'UNDERSTATED-INCOMING');
        $key = (string) Str::uuid();
        $entry = AdvertisingPaymentAdjustment::correct($payment, '500000', 'Actual receipt was 1000000', $key, 'increase');
        $this->assertSame('supplement', $entry->kind);
        $this->assertSame(500000, $payment->fresh()->amount);
        $this->assertSame(1000000, $invoice->fresh()->paidAmount());
        $this->assertSame('paid', $invoice->fresh()->billingState());
        $this->assertSame(1000000, AdvertisingPaymentAdjustment::availableForPayment($payment));
        $this->assertSame($entry->id, AdvertisingPaymentAdjustment::correct($payment, '500000', 'Actual receipt was 1000000', $key, 'increase')->id);
        $this->invalid(fn () => AdvertisingPaymentAdjustment::correct($payment, '500000', 'Actual receipt was 1000000', $key, 'decrease'), 'operation_key');
        $this->invalid(fn () => AdvertisingPaymentAdjustment::correct($payment, '1', 'Exceeds invoice', (string) Str::uuid(), 'increase'), 'amount');
        $this->actingAs($this->customer);
        $request = AdvertisingRefundRequest::requestFor($payment, '1000000', 'Return valid full receipt', (string) Str::uuid());
        $this->actingAs($this->admin);
        $request->decide('approve', 0)->complete('SUPPLEMENT-REFUND', 1, true);
        $this->assertSame(0, $invoice->fresh()->netReceivedAmount());
    }

    public function test_a_full_downward_correction_can_be_restored_with_an_independent_upward_document(): void
    {
        AdvertisingPaymentAdjustment::correct($this->payment, '1000000', 'Wrongly recorded', (string) Str::uuid());
        $this->assertSame(0, AdvertisingPaymentAdjustment::availableForPayment($this->payment));
        $this->get(route('advertising.invoices.show', $this->invoice))->assertOk()->assertSee('correction-direction-', false);
        AdvertisingPaymentAdjustment::correct($this->payment, '1000000', 'Correction was itself erroneous', (string) Str::uuid(), 'increase');
        $snapshot = AdvertisingInvoice::financialQuery()->findOrFail($this->invoice->id);
        $this->assertSame(1000000, $snapshot->correctedAmount());
        $this->assertSame(1000000, $snapshot->supplementedAmount());
        $this->assertSame(1000000, $snapshot->netReceivedAmount());
        $this->assertSame(2, $snapshot->adjustments()->count());
        $this->assertSame('paid', $snapshot->billingState());
    }

    private function unpaidInvoice(): AdvertisingInvoice
    {
        $this->actingAs($this->admin);

        return AdvertisingInvoice::create(['user_id' => $this->customer->id, 'description' => 'Gateway refund fixture', 'amount' => '1000000']);
    }

    private function gatewayAttempt(AdvertisingInvoice $invoice, bool &$paid): OnlinePaymentAttempt
    {
        $settings = app(PaymentGatewaySettings::class);
        $settings->zibal_enabled = true;
        $settings->zibal_sandbox = false;
        $settings->zibal_merchant = 'refund-test-merchant';
        $settings->save();
        Http::swap(new Factory);
        Http::preventStrayRequests();
        $orderId = null;
        Http::fake(function (Request $request) use (&$paid, &$orderId) {
            if (str_ends_with($request->url(), '/request')) {
                $orderId = $request['orderId'];

                return Http::response(['result' => 100, 'trackId' => 611111111]);
            }

            return Http::response($paid ? ['result' => 100, 'status' => 1, 'amount' => 1000000, 'orderId' => $orderId, 'refNumber' => 74444444] : ['result' => 202]);
        });
        $this->actingAs($this->customer);

        return OnlinePaymentAttempt::start($invoice, $this->customer, 'zibal');
    }

    private function customerRequest(string $amount = '200000', string $reason = 'Refund requested'): AdvertisingRefundRequest
    {
        $this->actingAs($this->customer);

        return AdvertisingRefundRequest::requestFor($this->payment, $amount, $reason, (string) Str::uuid());
    }

    private function invalid(callable $action, string $field): void
    {
        try {
            $action();
            $this->fail('Invalid financial change accepted.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey($field, $exception->errors());
        }
    }
}
