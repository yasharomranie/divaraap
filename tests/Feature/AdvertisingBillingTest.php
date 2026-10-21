<?php

declare(strict_types=1);

namespace Tests\Feature;

use Filament\Facades\Filament;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Modules\Advertising\Filament\Admin\Resources\AdvertisingInvoiceResource;
use Modules\Advertising\Filament\Admin\Resources\AdvertisingInvoiceResource\Pages\CreateAdvertisingInvoice;
use Modules\Advertising\Filament\Admin\Resources\AdvertisingInvoiceResource\Pages\ListAdvertisingInvoices;
use Modules\Advertising\Models\AdvertisingInvoice;
use Modules\Advertising\Models\AdvertisingPayment;
use Modules\Advertising\Models\Campaign;
use Modules\User\App\Models\User;
use Spatie\Activitylog\Models\Activity;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

final class AdvertisingBillingTest extends TestCase
{
    private User $admin;

    private User $customer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::where('email', 'a@a.com')->firstOrFail();
        $this->customer = User::factory()->create(['status' => 'active']);
        $this->customer->syncRoles([]);
        $this->actingAs($this->admin);
        Filament::setCurrentPanel(Filament::getPanel('admin'));
    }

    private function invoice(array $data = []): AdvertisingInvoice
    {
        return AdvertisingInvoice::create(array_replace(['user_id' => $this->customer->id, 'description' => 'Advertising service', 'amount' => '1000000'], $data));
    }

    private function invalid(callable $action, string $field): void
    {
        try {
            $action();
            $this->fail('Invalid financial action accepted');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey($field, $exception->errors());
        }
    }

    public function test_invoice_has_unique_number_customer_snapshot_and_rial_total(): void
    {
        $invoice = $this->invoice();
        $other = $this->invoice();
        $this->assertNotSame($invoice->number, $other->number);
        $this->assertSame('IRR', $invoice->currency);
        $this->assertSame('unpaid', $invoice->billingState());
        $this->assertSame(1000000, $invoice->balance());
        $this->assertSame($this->admin->id, $invoice->issued_by);
        $name = $this->customer->name;
        $this->customer->update(['name' => 'Changed customer']);
        $this->assertSame($name, $invoice->fresh()->customer['name']);
    }

    public function test_invalid_whole_amounts_and_inactive_customers_are_rejected(): void
    {
        foreach (['0', '-1', '1.5', '1e3', '1000000000001', '9999999999999999999999'] as $amount) {
            $this->invalid(fn () => $this->invoice(['amount' => $amount]), 'amount');
        }
        $this->invalid(fn () => $this->invoice(['description' => ' ']), 'description');
        $this->customer->update(['status' => 'suspended']);
        $this->invalid(fn () => $this->invoice(), 'user_id');
    }

    public function test_partial_and_full_receipts_are_audited_and_do_not_touch_campaign_cost(): void
    {
        $campaign = Campaign::create(['title' => 'Independent costs', 'is_active' => false, 'recorded_cost' => 42]);
        $invoice = $this->invoice(['campaign_id' => $campaign->id]);
        $first = AdvertisingPayment::recordReceived($invoice, '400000', 'bank-001', 'PRIVATE NOTE');
        $this->assertSame('BANK-001', $first->reference);
        $this->assertSame($this->admin->id, $first->recorded_by);
        $this->assertSame('partial', $invoice->fresh()->billingState());
        $this->assertSame(600000, $invoice->fresh()->balance());
        AdvertisingPayment::recordReceived($invoice, '600000', 'BANK-002');
        $this->assertSame('paid', $invoice->fresh()->billingState());
        $this->assertSame(0, $invoice->fresh()->balance());
        $this->assertSame(42, $campaign->fresh()->recorded_cost);
        $this->assertFalse($campaign->fresh()->is_active);
        $log = Activity::where('subject_type', AdvertisingPayment::class)->where('subject_id', $first->id)->firstOrFail();
        $this->assertSame($this->admin->id, $log->causer_id);
        $this->assertSame(400000, $log->attribute_changes['attributes']['amount']);
    }

    public function test_same_receipt_is_idempotent_and_cannot_be_reused_for_other_invoice_or_amount(): void
    {
        $invoice = $this->invoice();
        $first = AdvertisingPayment::recordReceived($invoice, '1000000', 'BANK-REPLAY');
        $replay = AdvertisingPayment::recordReceived($invoice, '1000000', ' bank-replay ');
        $this->assertSame($first->id, $replay->id);
        $this->assertSame(1, $invoice->payments()->count());
        $this->invalid(fn () => AdvertisingPayment::recordReceived($invoice, '1', 'BANK-REPLAY'), 'reference');
        $this->invalid(fn () => AdvertisingPayment::recordReceived($this->invoice(), '1000000', 'BANK-REPLAY'), 'reference');
    }

    public function test_invalid_and_overpayments_cannot_create_receipts(): void
    {
        $invoice = $this->invoice();
        foreach (['0', '-1', '1.5', '1e3', '1000000000001'] as $amount) {
            $this->invalid(fn () => AdvertisingPayment::recordReceived($invoice, $amount, 'BANK-INVALID'), 'amount');
        }
        $this->invalid(fn () => AdvertisingPayment::recordReceived($invoice, '1000001', 'BANK-OVER'), 'amount');
        $this->invalid(fn () => AdvertisingPayment::recordReceived($invoice, '1', '<script>'), 'reference');
        $this->assertSame(0, $invoice->payments()->count());
    }

    public function test_unpaid_invoice_can_be_voided_with_reason_but_paid_invoice_cannot(): void
    {
        $invoice = $this->invoice();
        $this->invalid(fn () => $invoice->voidInvoice(' '), 'reason');
        $invoice->voidInvoice('Issued by mistake');
        $this->assertSame('void', $invoice->billingState());
        $this->assertSame(0, $invoice->balance());
        $this->assertSame($this->admin->id, $invoice->voided_by);
        $this->invalid(fn () => AdvertisingPayment::recordReceived($invoice, '1', 'BANK-VOID'), 'amount');
        $partial = $this->invoice();
        AdvertisingPayment::recordReceived($partial, '1', 'BANK-PARTIAL');
        $this->invalid(fn () => $partial->voidInvoice('Not allowed'), 'reason');
    }

    public function test_financial_records_cannot_be_edited_or_deleted(): void
    {
        $invoice = $this->invoice();
        $receipt = AdvertisingPayment::recordReceived($invoice, '1', 'BANK-IMMUTABLE');
        $this->invalid(fn () => $invoice->update(['amount' => 2]), 'amount');
        $this->invalid(fn () => $invoice->delete(), 'invoice');
        $this->invalid(fn () => $invoice->forceDelete(), 'invoice');
        $this->invalid(fn () => $receipt->update(['amount' => 2]), 'payment');
        $this->invalid(fn () => $receipt->delete(), 'payment');
        $this->invalid(fn () => $receipt->forceDelete(), 'payment');
        $this->invalid(fn () => AdvertisingPayment::create(['invoice_id' => $invoice->id, 'amount' => 1]), 'payment');
    }

    public function test_owner_receipt_is_private_and_other_users_and_staff_cannot_read_it(): void
    {
        $invoice = $this->invoice(['description' => '<script>billing()</script>']);
        AdvertisingPayment::recordReceived($invoice, '1', 'BANK-PRIVATE', 'INTERNAL SECRET');
        $this->actingAs($this->customer);
        $this->get(route('advertising.invoices.index'))->assertOk()->assertSee($invoice->number);
        $this->get(route('advertising.invoices.show', $invoice))->assertOk()->assertHeader('Cache-Control', 'no-store, private')->assertHeader('X-Robots-Tag', 'noindex, nofollow')
            ->assertSee('&lt;script&gt;billing()&lt;/script&gt;', false)->assertDontSee('<script>billing()</script>', false)->assertDontSee('INTERNAL SECRET');
        $other = User::factory()->create(['status' => 'active']);
        $this->actingAs($other)->get(route('advertising.invoices.show', $invoice))->assertNotFound();
        $this->actingAs($other)->get(route('advertising.invoices.index'))->assertDontSee($invoice->number);
        $this->actingAs($this->admin)->get(route('advertising.invoices.show', $invoice))->assertOk();
        $this->actingAs($this->customer);
        $this->customer->update(['status' => 'suspended']);
        $this->get(route('advertising.invoices.show', $invoice))->assertForbidden();
    }

    public function test_non_admin_cannot_create_void_record_receipts_or_open_admin_resources(): void
    {
        $invoice = $this->invoice();
        $staff = User::factory()->create(['status' => 'active']);
        $staff->givePermissionTo('advertising.manage');
        $this->actingAs($staff);
        $this->assertFalse(AdvertisingInvoiceResource::canViewAny());
        $this->get(AdvertisingInvoiceResource::getUrl('index'))->assertForbidden();
        foreach ([fn () => $this->invoice(), fn () => $invoice->voidInvoice('Forbidden'), fn () => AdvertisingPayment::recordReceived($invoice, '1', 'BANK-FORBIDDEN')] as $action) {
            try {
                $action();
                $this->fail('Non-admin financial write accepted');
            } catch (HttpException $exception) {
                $this->assertSame(403, $exception->getStatusCode());
            }
        }
    }

    public function test_admin_can_issue_and_record_payment_through_filament(): void
    {
        Livewire::test(CreateAdvertisingInvoice::class)->fillForm(['user_id' => $this->customer->id, 'description' => 'Created in panel', 'amount' => '1000000'])->call('create')->assertHasNoFormErrors();
        $invoice = AdvertisingInvoice::where('description', 'Created in panel')->firstOrFail();
        Livewire::test(ListAdvertisingInvoices::class)->callTableAction('receive', $invoice, ['amount' => '1000000', 'reference' => 'BANK-FILAMENT', 'note' => 'Checked'])->assertHasNoActionErrors();
        $this->assertSame('paid', $invoice->fresh()->billingState());
    }
}
