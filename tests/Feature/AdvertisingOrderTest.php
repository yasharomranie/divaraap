<?php

declare(strict_types=1);

namespace Tests\Feature;

use Filament\Facades\Filament;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Modules\Advertising\Filament\Admin\Resources\AdvertisingOrderResource;
use Modules\Advertising\Filament\Admin\Resources\AdvertisingOrderResource\Pages\ListAdvertisingOrders;
use Modules\Advertising\Models\AdvertisingInvoice;
use Modules\Advertising\Models\AdvertisingOrder;
use Modules\Advertising\Models\AdvertisingPayment;
use Modules\Advertising\Models\BannerPlacement;
use Modules\Advertising\Models\Campaign;
use Modules\User\App\Models\User;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

final class AdvertisingOrderTest extends TestCase
{
    private User $customer;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->customer = User::factory()->create(['status' => 'active']);
        $this->customer->syncRoles([]);
        $this->admin = User::where('email', 'a@a.com')->firstOrFail();
        $this->actingAs($this->customer);
        Filament::setCurrentPanel(Filament::getPanel('admin'));
    }

    private function data(array $overrides = []): array
    {
        return array_replace(['request_key' => (string) Str::uuid(), 'title' => 'Order fixture', 'brief' => 'Advertising request fixture', 'placement' => 'home-top', 'goal' => 'awareness', 'duration_days' => '7', 'requested_budget' => '1000000'], $overrides);
    }

    private function order(): AdvertisingOrder
    {
        return AdvertisingOrder::submit($this->data());
    }

    private function invalid(callable $action, string $field): void
    {
        try {
            $action();
            $this->fail('Invalid order action accepted');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey($field, $exception->errors());
        }
    }

    public function test_customer_submission_is_scoped_immutable_and_idempotent(): void
    {
        $data = $this->data(['user_id' => $this->admin->id, 'state' => 'completed', 'invoice_id' => 999]);
        $order = AdvertisingOrder::submit($data);
        $this->assertSame($this->customer->id, $order->user_id);
        $this->assertSame('submitted', $order->state);
        $this->assertNull($order->invoice_id);
        $this->assertSame($order->id, AdvertisingOrder::submit($data)->id);
        $this->invalid(fn () => AdvertisingOrder::submit(array_replace($data, ['brief' => 'Different request'])), 'request_key');
        $this->invalid(fn () => $order->update(['state' => 'completed']), 'order');
        $this->invalid(fn () => $order->delete(), 'order');
    }

    public function test_unavailable_placements_and_invalid_fields_are_rejected(): void
    {
        foreach ([['placement' => 'category-top'], ['duration_days' => 0], ['duration_days' => 91], ['requested_budget' => -1], ['goal' => 'forged'], ['title' => ' '], ['brief' => str_repeat('a', 2001)]] as $input) {
            $key = array_key_first($input);
            $this->invalid(fn () => AdvertisingOrder::submit($this->data($input)), $key === 'title' ? 'title' : $key);
        }
        BannerPlacement::where('key', 'home-top')->update(['is_active' => false]);
        $this->invalid(fn () => $this->order(), 'placement');
    }

    public function test_full_order_flow_reuses_invoice_and_requires_acceptance_and_settlement(): void
    {
        $order = $this->order();
        $this->actingAs($this->admin);
        $campaign = Campaign::create(['title' => 'Order campaign', 'is_active' => false]);
        $order->quote('1200000', 'Seven days after coordination', $campaign->id);
        $invoiceId = $order->invoice_id;
        $order->quote('1200000', 'Seven days after coordination', $campaign->id);
        $this->assertSame($invoiceId, $order->invoice_id);
        $this->assertSame(1200000, $order->invoice->amount);
        $this->assertSame($this->customer->id, $order->invoice->user_id);
        $this->invalid(fn () => $order->complete($campaign->id), 'order');
        $this->actingAs($this->customer);
        $order->customerAction('accept');
        $order->customerAction('accept');
        $this->assertSame('accepted', $order->state);
        $this->actingAs($this->admin);
        $this->invalid(fn () => $order->complete($campaign->id), 'campaign_id');
        AdvertisingPayment::recordReceived($order->invoice, '1200000', 'ORDER-FULL');
        $order->complete($campaign->id);
        $this->assertSame('completed', $order->state);
        $this->assertNotNull($order->completed_at);
        $this->assertFalse($campaign->fresh()->is_active);
        $this->assertSame(0, $campaign->fresh()->recorded_cost);
    }

    public function test_customer_can_cancel_only_before_quote(): void
    {
        $order = $this->order();
        $order->customerAction('cancel');
        $order->customerAction('cancel');
        $this->assertSame('cancelled', $order->state);
        $this->actingAs($this->admin);
        $this->invalid(fn () => $order->quote('100', 'Terms'), 'order');
        $this->actingAs($this->customer);
        $quoted = $this->order();
        $this->actingAs($this->admin);
        $quoted->quote('100', 'Terms');
        $this->actingAs($this->customer);
        $this->invalid(fn () => $quoted->customerAction('cancel'), 'order');
    }

    public function test_rejection_voids_unpaid_invoice_atomically_but_cannot_erase_received_money(): void
    {
        $order = $this->order();
        $this->actingAs($this->admin);
        $order->quote('100', 'Terms');
        $order->reject('Unavailable inventory');
        $this->assertSame('rejected', $order->state);
        $this->assertSame('Terms', $order->manager_note);
        $this->assertSame('Unavailable inventory', $order->rejection_reason);
        $this->assertSame('void', $order->invoice->billingState());
        $this->actingAs($this->customer);
        $paid = $this->order();
        $this->actingAs($this->admin);
        $paid->quote('100', 'Terms');
        AdvertisingPayment::recordReceived($paid->invoice, '1', 'ORDER-PARTIAL');
        $this->invalid(fn () => $paid->reject('Cannot erase receipt'), 'reason');
        $this->assertSame('quoted', $paid->fresh()->state);
        $this->assertSame('partial', $paid->invoice->fresh()->billingState());
    }

    public function test_failed_quote_rolls_back_order_and_invoice_creation(): void
    {
        $order = $this->order();
        $count = AdvertisingInvoice::count();
        $this->actingAs($this->admin);
        $this->invalid(fn () => $order->quote('0', 'Terms'), 'amount');
        $this->assertSame($count, AdvertisingInvoice::count());
        $this->assertSame('submitted', $order->fresh()->state);
        $this->assertNull($order->fresh()->invoice_id);
    }

    public function test_private_routes_escape_text_and_prevent_foreign_owner_mutations(): void
    {
        $order = AdvertisingOrder::submit($this->data(['brief' => '<script>order()</script>']));
        $this->get(route('advertising.orders.show', $order))->assertOk()->assertHeader('Cache-Control', 'no-store, private')
            ->assertSee('&lt;script&gt;order()&lt;/script&gt;', false)->assertDontSee('<script>order()</script>', false);
        $other = User::factory()->create(['status' => 'active']);
        $this->actingAs($other)->get(route('advertising.orders.show', $order))->assertNotFound();
        $this->post(route('advertising.orders.cancel', $order))->assertNotFound();
        $this->get(route('advertising.orders.index'))->assertDontSee($order->title);
        $this->assertSame('submitted', $order->fresh()->state);
    }

    public function test_nonadmin_cannot_quote_reject_or_complete_even_with_advertising_permission(): void
    {
        $order = $this->order();
        $this->customer->givePermissionTo('advertising.manage');
        $this->assertFalse(AdvertisingOrderResource::canViewAny());
        $this->get(AdvertisingOrderResource::getUrl('index'))->assertForbidden();
        foreach ([fn () => $order->quote('100', 'Terms'), fn () => $order->reject('No'), fn () => $order->complete(1)] as $action) {
            try {
                $action();
                $this->fail('Nonadmin order review accepted');
            } catch (HttpException $exception) {
                $this->assertSame(403, $exception->getStatusCode());
            }
        }
    }

    public function test_void_invoice_cannot_be_accepted_and_campaign_cannot_be_substituted(): void
    {
        $order = $this->order();
        $this->actingAs($this->admin);
        $order->quote('100', 'Terms');
        $order->invoice->voidInvoice('Mistake');
        $this->actingAs($this->customer);
        $this->invalid(fn () => $order->customerAction('accept'), 'order');
        $next = $this->order();
        $this->actingAs($this->admin);
        $campaign = Campaign::create(['title' => 'Quoted campaign']);
        $other = Campaign::create(['title' => 'Different campaign']);
        $next->quote('100', 'Terms', $campaign->id);
        $this->actingAs($this->customer);
        $next->customerAction('accept');
        $this->actingAs($this->admin);
        AdvertisingPayment::recordReceived($next->invoice, '100', 'ORDER-CAMPAIGN');
        $this->invalid(fn () => $next->complete($other->id), 'campaign_id');
    }

    public function test_http_submission_and_admin_quote_action_work(): void
    {
        $this->get(route('advertising.orders.create'))->assertOk();
        $this->post(route('advertising.orders.store'), $this->data())->assertRedirect();
        $order = AdvertisingOrder::where('user_id', $this->customer->id)->firstOrFail();
        $this->actingAs($this->admin);
        Livewire::test(ListAdvertisingOrders::class)->callTableAction('quote', $order, ['amount' => '100', 'terms' => 'Panel quote', 'campaign_id' => null])->assertHasNoActionErrors();
        $this->assertSame('quoted', $order->fresh()->state);
        $this->actingAs($this->customer)->post(route('advertising.orders.accept', $order))->assertRedirect();
        $this->assertSame('accepted', $order->fresh()->state);
        $this->customer->update(['status' => 'suspended']);
        $this->get(route('advertising.orders.create'))->assertForbidden();
        $this->post(route('advertising.orders.store'), $this->data())->assertForbidden();
    }
}
