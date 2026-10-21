<?php

declare(strict_types=1);

namespace Tests\Feature;

use Filament\Facades\Filament;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Modules\Advertising\Models\AdvertisingInvoice;
use Modules\Advertising\Models\AdvertisingPayment;
use Modules\Category\Models\Category;
use Modules\Listing\Models\Listing;
use Modules\Promotion\Filament\Admin\Resources\SellerPackagePurchaseResource\Pages\ListPurchases;
use Modules\Promotion\Filament\Admin\Resources\SellerPackageResource;
use Modules\Promotion\Filament\Admin\Resources\SellerPackageResource\Pages\CreatePackage;
use Modules\Promotion\Models\PromotionOrder;
use Modules\Promotion\Models\PromotionPlan;
use Modules\Promotion\Models\SellerPackage;
use Modules\Promotion\Models\SellerPackagePurchase;
use Modules\Promotion\Models\SellerPackageRedemption;
use Modules\User\App\Models\User;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

final class SellerPackageTest extends TestCase
{
    private User $admin;

    private User $seller;

    private SellerPackage $package;

    private PromotionPlan $plan;

    private Listing $listing;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::where('email', 'a@a.com')->firstOrFail();
        $this->seller = User::factory()->create(['status' => 'active']);
        $this->seller->syncRoles([]);
        $this->actingAs($this->admin);
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $this->plan = PromotionPlan::create(['name' => 'Captured promotion', 'slug' => (string) Str::uuid(), 'price' => 100, 'currency' => 'IRR', 'duration_days' => 3, 'is_active' => true]);
        $this->package = SellerPackage::create(['name' => 'Seller fixture', 'promotion_plan_id' => $this->plan->id, 'price' => 1000000, 'credits' => 2, 'validity_days' => 30, 'is_active' => true]);
        $this->listing = Listing::unguarded(fn () => Listing::create(['title' => 'Package listing', 'description' => 'Fixture', 'price' => 100, 'currency' => 'IRR', 'user_id' => $this->seller->id, 'category_id' => Category::firstOrFail()->id, 'slug' => (string) Str::uuid(), 'status' => 'active']));
    }

    private function requestPackage(): SellerPackagePurchase
    {
        $this->actingAs($this->seller);

        return SellerPackagePurchase::requestPackage($this->package->id, SellerPackage::version($this->package->offer()));
    }

    private function activate(): SellerPackagePurchase
    {
        $purchase = $this->requestPackage();
        $this->actingAs($this->admin);
        $purchase->review('invoice');
        AdvertisingPayment::recordReceived($purchase->invoice, '1000000', 'PACKAGE-'.$purchase->id);
        $purchase->review('activate');
        $this->actingAs($this->seller);

        return $purchase;
    }

    private function invalid(callable $action, string $field): void
    {
        try {
            $action();
            $this->fail('Invalid package action accepted');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey($field, $exception->errors());
        }
    }

    public function test_request_is_idempotent_and_captures_price_and_credit_terms(): void
    {
        $purchase = $this->requestPackage();
        $this->assertSame($purchase->id, $this->requestPackage()->id);
        $this->assertSame('requested', $purchase->state);
        $this->assertSame(2, $purchase->remaining());
        $this->assertNull($purchase->invoice_id);
        $this->actingAs($this->admin);
        $this->package->update(['price' => 2000000, 'credits' => 10]);
        $this->plan->update(['duration_days' => 30]);
        $this->assertSame(1000000, $purchase->fresh()->snapshot['price']);
        $this->assertSame(3, $purchase->fresh()->snapshot['promotion_days']);
        $purchase->review('invoice');
        $this->assertSame(1000000, $purchase->invoice->amount);
    }

    public function test_changed_offer_invalid_plan_and_inactive_catalog_are_safe(): void
    {
        $version = SellerPackage::version($this->package->offer());
        $this->package->update(['price' => 2000000]);
        $this->actingAs($this->seller);
        $this->invalid(fn () => SellerPackagePurchase::requestPackage($this->package->id, $version), 'package');
        $this->plan->update(['is_active' => false]);
        $this->assertSame([], SellerPackage::catalog());
        $this->invalid(fn () => SellerPackagePurchase::requestPackage($this->package->id, $version), 'package');
        $this->actingAs($this->admin);
        $this->invalid(fn () => $this->package->update(['price' => 0]), 'price');
    }

    public function test_activation_requires_full_receipt_and_starts_fixed_nonrenewing_period(): void
    {
        $purchase = $this->requestPackage();
        $this->actingAs($this->admin);
        $purchase->review('invoice');
        $this->invalid(fn () => $purchase->review('activate'), 'package');
        AdvertisingPayment::recordReceived($purchase->invoice, '400000', 'PACKAGE-PARTIAL');
        $this->invalid(fn () => $purchase->review('activate'), 'package');
        AdvertisingPayment::recordReceived($purchase->invoice, '600000', 'PACKAGE-REST');
        $purchase->review('activate');
        $this->assertSame('active', $purchase->displayState());
        $this->assertEqualsWithDelta(30, $purchase->starts_at->diffInDays($purchase->ends_at), 0.01);
        $end = $purchase->ends_at->timestamp;
        $this->invalid(fn () => $purchase->review('activate'), 'package');
        $this->assertSame($end, $purchase->fresh()->ends_at->timestamp);
    }

    public function test_credit_consumption_creates_existing_promotion_once_and_uses_snapshot_after_catalog_change(): void
    {
        $purchase = $this->activate();
        $this->actingAs($this->admin);
        $this->plan->update(['duration_days' => 30, 'is_active' => false]);
        $this->package->update(['is_active' => false]);
        $this->actingAs($this->seller);
        $key = (string) Str::uuid();
        $first = SellerPackageRedemption::consume($purchase->id, $this->listing->id, $key);
        $this->assertSame($first->id, SellerPackageRedemption::consume($purchase->id, $this->listing->id, $key)->id);
        $this->assertSame(1, $purchase->fresh()->remaining());
        $promotion = PromotionOrder::findOrFail($first->promotion_order_id);
        $this->assertSame('0.00', $promotion->amount);
        $this->assertSame('IRR', $promotion->currency);
        $this->assertSame('Captured promotion', $promotion->plan_name);
        $this->assertEqualsWithDelta(3, $promotion->starts_at->diffInDays($promotion->ends_at), 0.01);
        $this->assertContains($this->listing->id, PromotionOrder::activeListingIds());
        $this->assertSame(1000000, $purchase->invoice->amount);
    }

    public function test_existing_promotions_are_not_replaced_or_charged_a_credit(): void
    {
        $purchase = $this->activate();
        $old = PromotionOrder::open($this->plan, $this->seller->id, $this->listing->id);
        $this->invalid(fn () => SellerPackageRedemption::consume($purchase->id, $this->listing->id, (string) Str::uuid()), 'listing_id');
        $this->assertSame(2, $purchase->fresh()->remaining());
        $this->assertSame('active', $old->fresh()->status);
        $this->assertSame(0, $purchase->redemptions()->count());
    }

    public function test_expired_and_exhausted_packages_cannot_be_consumed(): void
    {
        $purchase = $this->activate();
        SellerPackageRedemption::consume($purchase->id, $this->listing->id, (string) Str::uuid());
        $this->travelTo($purchase->ends_at);
        $this->assertSame('expired', $purchase->fresh()->displayState());
        $this->invalid(fn () => SellerPackageRedemption::consume($purchase->id, $this->listing->id, (string) Str::uuid()), 'package');
        $this->assertSame(1, $purchase->fresh()->remaining());
    }

    public function test_exhausted_credits_and_reused_key_for_another_listing_are_rejected(): void
    {
        $purchase = $this->activate();
        $second = $this->listing->replicate();
        $second->slug = (string) Str::uuid();
        $second->save();
        $key = (string) Str::uuid();
        SellerPackageRedemption::consume($purchase->id, $this->listing->id, $key);
        $this->invalid(fn () => SellerPackageRedemption::consume($purchase->id, $second->id, $key), 'package');
        SellerPackageRedemption::consume($purchase->id, $second->id, (string) Str::uuid());
        $this->assertSame('spent', $purchase->fresh()->displayState());
        $this->assertSame(0, $purchase->fresh()->remaining());
        $this->invalid(fn () => SellerPackageRedemption::consume($purchase->id, $second->id, (string) Str::uuid()), 'package');
        $this->assertSame(2, $purchase->redemptions()->count());
    }

    public function test_catalog_markup_escapes_package_text_and_admin_can_create_catalog_entry(): void
    {
        $this->package->update(['name' => '<script>package()</script>']);
        $this->actingAs($this->seller)->get(route('seller-packages.index'))->assertOk()->assertSee('&lt;script&gt;package()&lt;/script&gt;', false)->assertDontSee('<script>package()</script>', false);
        $this->actingAs($this->admin);
        Livewire::test(CreatePackage::class)
            ->fillForm(['name' => 'Panel package', 'promotion_plan_id' => $this->plan->id, 'price' => '100000', 'credits' => 3, 'validity_days' => 10, 'is_active' => true])->call('create')->assertHasNoFormErrors();
        $this->assertTrue(SellerPackage::where('name', 'Panel package')->firstOrFail()->is_active);
    }

    public function test_foreign_paused_and_expired_listings_cannot_spend_credits(): void
    {
        $purchase = $this->activate();
        $this->listing->forceFill(['status' => 'paused'])->save();
        $this->invalid(fn () => SellerPackageRedemption::consume($purchase->id, $this->listing->id, (string) Str::uuid()), 'listing_id');
        $this->listing->forceFill(['status' => 'active', 'expires_at' => now()->subMinute()])->save();
        $this->invalid(fn () => SellerPackageRedemption::consume($purchase->id, $this->listing->id, (string) Str::uuid()), 'listing_id');
        $other = User::factory()->create(['status' => 'active']);
        $this->actingAs($other)->get(route('seller-packages.show', $purchase))->assertNotFound();
        $this->post(route('seller-packages.redeem', $purchase), ['listing_id' => $this->listing->id, 'request_key' => (string) Str::uuid()])->assertNotFound();
        $this->assertSame(2, $purchase->fresh()->remaining());
    }

    public function test_unpaid_cancellation_voids_invoice_but_received_money_cannot_be_erased(): void
    {
        $purchase = $this->requestPackage();
        $this->actingAs($this->admin);
        $purchase->review('invoice');
        $purchase->review('cancel', 'Customer request');
        $this->assertSame('cancelled', $purchase->state);
        $this->assertSame('void', $purchase->invoice->fresh()->billingState());
        $other = $this->requestPackage();
        $this->actingAs($this->admin);
        $other->review('invoice');
        AdvertisingPayment::recordReceived($other->invoice, '1', 'PACKAGE-NO-CANCEL');
        $this->invalid(fn () => $other->review('cancel', 'No refund workflow'), 'reason');
        $this->assertSame('invoiced', $other->fresh()->state);
    }

    public function test_private_views_immutable_history_and_nonadmin_review_are_enforced(): void
    {
        $purchase = $this->activate();
        $this->get(route('seller-packages.show', $purchase))->assertOk()->assertHeader('Cache-Control', 'no-store, private');
        $this->invalid(fn () => $purchase->update(['used_credits' => 0]), 'package');
        $this->invalid(fn () => $purchase->delete(), 'package');
        $this->assertFalse(SellerPackageResource::canViewAny());
        try {
            $purchase->review('activate');
            $this->fail('Owner could activate');
        } catch (HttpException $exception) {
            $this->assertSame(403, $exception->getStatusCode());
        }
        $this->seller->update(['status' => 'suspended']);
        $this->get(route('seller-packages.index'))->assertForbidden();
    }

    public function test_admin_invoice_action_and_customer_request_route_work(): void
    {
        $this->actingAs($this->seller);
        $this->post(route('seller-packages.request', $this->package), ['version' => SellerPackage::version($this->package->offer())])->assertRedirect();
        $purchase = SellerPackagePurchase::where('user_id', $this->seller->id)->firstOrFail();
        $this->actingAs($this->admin);
        Livewire::test(ListPurchases::class)->callTableAction('invoice', $purchase)->assertHasNoActionErrors();
        $this->assertSame('invoiced', $purchase->fresh()->state);
        $this->assertSame(1, AdvertisingInvoice::where('user_id', $this->seller->id)->count());
    }
}
