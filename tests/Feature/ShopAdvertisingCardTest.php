<?php

declare(strict_types=1);

namespace Tests\Feature;

use Filament\Facades\Filament;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Modules\Advertising\Filament\Admin\Resources\BannerResource\Pages\EditBanner;
use Modules\Advertising\Models\AdvertisingEvent;
use Modules\Advertising\Models\Banner;
use Modules\Advertising\Models\BannerPlacement;
use Modules\Advertising\Models\Campaign;
use Modules\Advertising\Support\AdvertisingToken;
use Modules\Advertising\Support\BannerRenderer;
use Modules\User\App\Models\Profile;
use Modules\User\App\Models\User;
use Tests\TestCase;

final class ShopAdvertisingCardTest extends TestCase
{
    private User $seller;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
        $image = UploadedFile::fake()->image('shop.jpg');
        Storage::disk('public')->put('banners/shop-fixture.jpg', file_get_contents($image->getRealPath()));
        $this->seller = User::create(['name' => 'PRIVATE ACCOUNT NAME', 'email' => Str::uuid().'@example.test', 'password' => 'fixture-password', 'status' => 'active']);
        Profile::saveShopFor($this->seller, ['shop_enabled' => true, 'shop_name' => 'Public shop fixture', 'shop_description' => 'Shop introduction', 'shop_hours' => '9–18'], Profile::shopVersion($this->seller));
    }

    private function card(array $data = []): Banner
    {
        return Banner::create(array_replace(['title' => 'Approved shop headline', 'alt' => 'Shop photo', 'image' => 'banners/shop-fixture.jpg', 'creative_format' => 'shop', 'shop_user_id' => $this->seller->id, 'device' => 'both', 'is_active' => true, 'review_status' => 'approved'], $data));
    }

    private function assign(Banner $card): void
    {
        BannerPlacement::where('key', 'home-top')->firstOrFail()->assignments()->create(['banner_id' => $card->id, 'priority' => 1]);
    }

    public function test_card_uses_existing_shop_link_and_only_approved_public_snapshot(): void
    {
        $card = $this->card(['url' => 'https://unrelated.example']);
        $this->assign($card);
        $this->assertSame(route('sellers.show', $this->seller->id), $card->destination());
        $this->assertSame('Public shop fixture', $card->shop_snapshot['name']);
        $this->get('/')->assertOk()->assertSee('data-ad-shop="'.$this->seller->id.'"', false)->assertSee('Public shop fixture')->assertSee('Shop introduction')->assertDontSee('PRIVATE ACCOUNT NAME')->assertDontSee($this->seller->email)->assertDontSee('https://unrelated.example');
    }

    public function test_changed_or_disabled_shop_or_suspended_owner_suppresses_delivery_and_warns(): void
    {
        $card = $this->card();
        $this->assign($card);
        $profile = Profile::detailsForUser($this->seller);
        $profile->forceFill(['shop_name' => 'Changed public shop'])->save();
        $this->assertFalse($card->fresh()->shopCreativeEligible());
        $this->assertNull($card->fresh()->destination());
        $this->assertSame([], (new BannerRenderer)->at('home-top'));
        $this->assertContains('shop_unavailable', array_column(Banner::healthIssues(), 'code'));
        $card->save();
        $this->assertTrue($card->fresh()->shopCreativeEligible());
        $profile->forceFill(['shop_enabled' => false])->save();
        $this->assertFalse($card->fresh()->shopCreativeEligible());
        $card->update(['is_active' => false]);
        $profile->forceFill(['shop_enabled' => true])->save();
        $this->seller->update(['status' => 'suspended']);
        $this->assertFalse($card->fresh()->shopCreativeEligible());
    }

    public function test_staff_change_reenters_moderation_and_approval_keeps_shop_binding(): void
    {
        $card = $this->card();
        $manager = User::create(['name' => 'Staff', 'email' => Str::uuid().'@example.test', 'password' => 'fixture-password', 'status' => 'active']);
        $manager->syncRoles(['advertising-manager']);
        $this->actingAs($manager);
        Profile::detailsForUser($this->seller)->forceFill(['shop_description' => 'Changed public introduction'])->save();
        $card->update(['daily_frequency_cap' => 5]);
        $this->assertSame('pending', $card->fresh()->review_status);
        $this->assertFalse($card->fresh()->is_active);
        $manager->syncRoles(['moderator']);
        $this->actingAs($manager);
        Banner::moderationQueue()->firstWhere('id', $card->id)->moderate(true);
        $this->assertSame('shop', $card->fresh()->creative_format);
        $this->assertSame($this->seller->id, (int) $card->fresh()->shop_user_id);
        $this->assertTrue($card->fresh()->shopCreativeEligible());
    }

    public function test_campaign_targeting_schedule_device_priority_and_capacity_are_reused(): void
    {
        $campaign = Campaign::create(['title' => 'Shop campaign', 'is_active' => true, 'page_paths' => ['/allowed']]);
        $card = $this->card(['campaign_id' => $campaign->id]);
        $this->assign($card);
        $this->assertFalse($card->eligibleForCampaign(['path' => 'other']));
        $this->assertTrue($card->eligibleForCampaign(['path' => 'allowed']));
        $card->update(['starts_at' => now()->addDay()]);
        $this->assertSame([], (new BannerRenderer)->at('home-top', context: ['path' => 'allowed']));
        $card->update(['starts_at' => null, 'device' => 'mobile']);
        $result = (new BannerRenderer)->at('home-top', context: ['path' => 'allowed']);
        $this->assertSame($card->id, $result['mobile']->id);
        $this->assertArrayNotHasKey('desktop', $result);
    }

    public function test_metrics_accept_shop_clicks_deduplicate_and_drop_events_after_shop_changes(): void
    {
        $card = $this->card();
        $this->assign($card);
        $this->get('/')->assertOk();
        $token = AdvertisingToken::issue($card, 'home-top', 'both', []);
        $this->withCookie(config('session.cookie'), request()->session()->getId());
        $this->travel(2)->seconds();
        $this->withCredentials()->postJson(route('advertising.events'), ['token' => $token, 'kind' => 'click', 'device' => 'mobile'])->assertNoContent();
        $this->withCredentials()->postJson(route('advertising.events'), ['token' => $token, 'kind' => 'click', 'device' => 'mobile'])->assertNoContent();
        $this->assertSame(1, AdvertisingEvent::where('banner_id', $card->id)->count());
        Profile::detailsForUser($this->seller)->forceFill(['shop_enabled' => false])->save();
        $this->withCredentials()->postJson(route('advertising.events'), ['token' => $token, 'kind' => 'impression', 'device' => 'mobile'])->assertNoContent();
        $this->assertSame(1, AdvertisingEvent::where('banner_id', $card->id)->count());
        $this->travelBack();
    }

    public function test_public_shop_labels_are_escaped_and_snapshot_order_does_not_affect_eligibility(): void
    {
        Profile::detailsForUser($this->seller)->forceFill(['shop_name' => '<script>bad()</script>'])->save();
        $card = $this->card();
        $this->assign($card);
        $this->get('/')->assertOk()->assertSee('&lt;script&gt;bad()&lt;/script&gt;', false)->assertDontSee('<script>bad()</script>', false);
        $snapshot = $card->shop_snapshot;
        ksort($snapshot);
        $card->forceFill(['shop_snapshot' => $snapshot]);
        $this->assertTrue($card->shopCreativeEligible());
        $this->assertSame([], Profile::advertisingShops([]));
    }

    public function test_filament_can_select_shop_format_and_regular_users_cannot_manage_cards(): void
    {
        $card = $this->card();
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $this->actingAs(User::where('email', 'a@a.com')->firstOrFail());
        Livewire::test(EditBanner::class, ['record' => $card->id])->fillForm(['creative_format' => 'shop', 'shop_user_id' => $this->seller->id])->call('save')->assertHasNoFormErrors();
        $this->assertSame('shop', $card->fresh()->creative_format);
        $this->assertArrayHasKey($this->seller->id, Profile::advertisingShopOptions('Public shop'));
        $this->actingAs($this->seller)->get('/admin/banners/'.$card->id.'/edit')->assertForbidden();
    }
}
