<?php

declare(strict_types=1);

namespace Tests\Feature;

use Filament\Facades\Filament;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Modules\Advertising\Filament\Admin\Resources\AdvertisingBundleResource;
use Modules\Advertising\Filament\Admin\Resources\AdvertisingBundleResource\Pages\CreateBundle;
use Modules\Advertising\Filament\Admin\Resources\AdvertisingOrderResource\Pages\ListAdvertisingOrders;
use Modules\Advertising\Models\AdvertisingBundle;
use Modules\Advertising\Models\AdvertisingInvoice;
use Modules\Advertising\Models\AdvertisingOrder;
use Modules\Advertising\Models\AdvertisingPayment;
use Modules\Advertising\Models\BannerPlacement;
use Modules\Advertising\Models\Campaign;
use Modules\Advertising\Models\PlacementReservation;
use Modules\User\App\Models\User;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

final class AdvertisingBundleTest extends TestCase
{
    private User $admin;

    private User $customer;

    private AdvertisingBundle $bundle;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::where('email', 'a@a.com')->firstOrFail();
        $this->customer = User::factory()->create(['status' => 'active']);
        $this->customer->syncRoles([]);
        $this->actingAs($this->admin);
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $this->bundle = AdvertisingBundle::create(['name' => 'Bundle fixture', 'price' => '1000000', 'items' => $this->items(), 'is_active' => true]);
    }

    private function items(): array
    {
        return [['placement' => 'home-top', 'device' => 'both', 'duration_days' => 7], ['placement' => 'listing-details', 'device' => 'mobile', 'duration_days' => 3]];
    }

    private function request(array $overrides = []): array
    {
        return array_replace(['request_key' => (string) Str::uuid(), 'brief' => 'Customer advertising brief'], $overrides);
    }

    private function order(): AdvertisingOrder
    {
        $this->actingAs($this->customer);

        return AdvertisingOrder::submitBundle($this->bundle->id, AdvertisingBundle::version($this->bundle->offer()), $this->request());
    }

    private function paidOrder(): AdvertisingOrder
    {
        $order = $this->order();
        $this->actingAs($this->admin);
        $order->quote('1000000', 'Captured bundle service');
        $this->actingAs($this->customer);
        $order->customerAction('accept');
        $this->actingAs($this->admin);
        AdvertisingPayment::recordReceived($order->invoice, '1000000', 'BUNDLE-'.$order->id);

        return $order;
    }

    private function invalid(callable $action, string $field): void
    {
        try {
            $action();
            $this->fail('Invalid bundle action accepted');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey($field, $exception->errors());
        }
    }

    public function test_catalog_rejects_duplicate_unsupported_and_incompatible_components_and_fractional_price(): void
    {
        foreach ([['items' => [$this->items()[0]]], ['items' => [$this->items()[0], $this->items()[0]]], ['price' => '1.5'], ['price' => '0']] as $invalid) {
            $this->invalid(fn () => AdvertisingBundle::create(array_replace(['name' => 'Bad bundle', 'price' => 100, 'items' => $this->items(), 'is_active' => true], $invalid)), array_key_first($invalid) === 'items' && count($invalid['items']) > 1 ? 'items.0.placement' : array_key_first($invalid));
        }
        BannerPlacement::where('key', 'listing-details')->update(['device' => 'desktop']);
        $this->assertSame([], AdvertisingBundle::catalog());
        $this->invalid(fn () => $this->bundle->save(), 'items');
    }

    public function test_one_request_captures_order_terms_and_replays_without_duplicate_orders(): void
    {
        $request = $this->request();
        $version = AdvertisingBundle::version($this->bundle->offer());
        $this->actingAs($this->customer);
        $order = AdvertisingOrder::submitBundle($this->bundle->id, $version, $request);
        $this->assertSame($order->id, AdvertisingOrder::submitBundle($this->bundle->id, $version, $request)->id);
        $this->assertSame($this->customer->id, $order->user_id);
        $this->assertCount(2, $order->bundle_snapshot['items']);
        $this->assertSame('home-top', $order->placement);
        $this->assertSame(7, $order->duration_days);
        $this->actingAs($this->admin);
        $this->bundle->update(['price' => 2000000]);
        $this->assertSame(1000000, $order->fresh()->bundle_snapshot['price']);
        $order->quote('1000000', 'Existing captured quote');
        $this->assertSame(1000000, $order->invoice->amount);
        $this->assertSame(1, AdvertisingInvoice::where('user_id', $this->customer->id)->count());
    }

    public function test_stale_offer_and_inactive_placement_cannot_create_new_requests(): void
    {
        $version = AdvertisingBundle::version($this->bundle->offer());
        $this->bundle->update(['price' => 2000000]);
        $this->actingAs($this->customer);
        $this->invalid(fn () => AdvertisingOrder::submitBundle($this->bundle->id, $version, $this->request()), 'bundle');
        BannerPlacement::where('key', 'home-top')->update(['is_active' => false]);
        $this->invalid(fn () => AdvertisingOrder::submitBundle($this->bundle->id, $version, $this->request()), 'bundle');
        $this->assertSame(0, AdvertisingOrder::where('user_id', $this->customer->id)->count());
    }

    public function test_general_order_identity_cannot_be_hijacked_into_bundle(): void
    {
        $input = $this->request();
        $this->actingAs($this->customer);
        $normal = AdvertisingOrder::submit($input + ['title' => $this->bundle->name, 'goal' => 'awareness', 'placement' => 'home-top', 'duration_days' => 7, 'requested_budget' => 1000000]);
        $this->invalid(fn () => AdvertisingOrder::submitBundle($this->bundle->id, AdvertisingBundle::version($this->bundle->offer()), $input), 'request_key');
        $this->assertNull($normal->fresh()->bundle_id);
    }

    public function test_bundle_invoice_price_cannot_be_overridden(): void
    {
        $order = $this->order();
        $this->actingAs($this->admin);
        $this->invalid(fn () => $order->quote('1', 'Forged discount'), 'amount');
        $this->assertSame('submitted', $order->fresh()->state);
        $this->assertNull($order->fresh()->invoice_id);
    }

    public function test_all_components_and_primary_campaign_are_required_for_completion(): void
    {
        $order = $this->paidOrder();
        $primary = Campaign::create(['title' => 'Primary', 'is_active' => false]);
        $second = Campaign::create(['title' => 'Secondary', 'is_active' => false]);
        $this->invalid(fn () => $order->complete($primary->id), 'campaign_id');
        $order->fulfillBundleItem(1, $primary->id);
        $order->fulfillBundleItem(1, $primary->id);
        $this->assertCount(1, $order->bundle_fulfillment);
        $this->invalid(fn () => $order->fulfillBundleItem(1, $second->id), 'item_id');
        $this->invalid(fn () => $order->complete($primary->id), 'campaign_id');
        $order->fulfillBundleItem(2, $second->id);
        $this->invalid(fn () => $order->complete($second->id), 'campaign_id');
        $order->complete($primary->id);
        $this->assertSame('completed', $order->state);
        $this->assertFalse($primary->fresh()->is_active);
        $this->assertSame(0, $primary->fresh()->recorded_cost);
    }

    public function test_fulfillment_requires_acceptance_full_settlement_and_valid_component(): void
    {
        $order = $this->order();
        $this->actingAs($this->admin);
        $campaign = Campaign::create(['title' => 'Fulfillment']);
        $this->invalid(fn () => $order->fulfillBundleItem(1, $campaign->id), 'order');
        $order->quote('1000000', 'Terms');
        $this->actingAs($this->customer);
        $order->customerAction('accept');
        $this->actingAs($this->admin);
        $this->invalid(fn () => $order->fulfillBundleItem(1, $campaign->id), 'item_id');
        AdvertisingPayment::recordReceived($order->invoice, '1000000', 'BUNDLE-GATES');
        $this->invalid(fn () => $order->fulfillBundleItem(0, $campaign->id), 'item_id');
        $this->invalid(fn () => $order->fulfillBundleItem(3, $campaign->id), 'item_id');
    }

    public function test_secondary_reservation_uses_component_device_and_duration(): void
    {
        $order = $this->paidOrder();
        $start = now()->addDay()->startOfDay();
        $data = ['request_key' => (string) Str::uuid(), 'title' => 'Secondary booking', 'placement_id' => BannerPlacement::where('key', 'listing-details')->firstOrFail()->id, 'order_id' => $order->id, 'device' => 'mobile', 'starts_at' => $start->toIso8601String(), 'ends_at' => $start->copy()->addDays(3)->toIso8601String()];
        $this->invalid(fn () => PlacementReservation::reserve(array_replace($data, ['device' => 'both'])), 'order_id');
        $this->invalid(fn () => PlacementReservation::reserve(array_replace($data, ['ends_at' => $start->copy()->addDays(4)->toIso8601String()])), 'order_id');
        $booking = PlacementReservation::reserve($data);
        $this->assertSame($order->id, $booking->order_id);
    }

    public function test_owner_views_escape_text_and_nonadmin_cannot_manage_or_fulfill(): void
    {
        $this->bundle->update(['name' => '<script>bundle()</script>']);
        $order = $this->order();
        $this->get(route('advertising.bundles.index'))->assertOk()->assertHeader('Cache-Control', 'no-store, private')->assertSee('&lt;script&gt;bundle()&lt;/script&gt;', false)->assertDontSee('<script>bundle()</script>', false);
        $this->get(route('advertising.orders.show', $order))->assertOk()->assertSee('data-bundle-item="2"', false);
        $this->assertFalse(AdvertisingBundleResource::canViewAny());
        try {
            $order->fulfillBundleItem(1, 1);
            $this->fail('Customer fulfillment accepted');
        } catch (HttpException $exception) {
            $this->assertSame(403, $exception->getStatusCode());
        }
        $other = User::factory()->create(['status' => 'active']);
        $this->actingAs($other)->get(route('advertising.orders.show', $order))->assertNotFound();
    }

    public function test_http_request_admin_catalog_and_item_confirmation_actions_work(): void
    {
        $this->actingAs($this->customer)->post(route('advertising.bundles.store', $this->bundle), $this->request(['version' => AdvertisingBundle::version($this->bundle->offer())]))->assertRedirect();
        $order = AdvertisingOrder::where('user_id', $this->customer->id)->firstOrFail();
        $this->actingAs($this->admin);
        $order->quote('1000000', 'Terms');
        $this->actingAs($this->customer);
        $order->customerAction('accept');
        $this->actingAs($this->admin);
        AdvertisingPayment::recordReceived($order->invoice, '1000000', 'BUNDLE-PANEL');
        $campaign = Campaign::create(['title' => 'Panel campaign']);
        Livewire::test(ListAdvertisingOrders::class)->callTableAction('fulfill_item', $order, ['item_id' => 1, 'campaign_id' => $campaign->id])->assertHasNoActionErrors();
        $this->assertCount(1, $order->fresh()->bundle_fulfillment);
        Livewire::test(CreateBundle::class)->fillForm(['name' => 'Panel bundle', 'price' => 500, 'items' => $this->items(), 'is_active' => true])->call('create')->assertHasNoFormErrors();
        $this->assertTrue(AdvertisingBundle::where('name', 'Panel bundle')->firstOrFail()->is_active);
    }
}
