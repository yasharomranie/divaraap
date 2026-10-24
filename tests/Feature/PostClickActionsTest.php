<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Str;
use Modules\Admin\App\Settings\OperationsSettings;
use Modules\Advertising\Models\AdvertisingEvent;
use Modules\Advertising\Models\Banner;
use Modules\Advertising\Models\BannerPlacement;
use Modules\Advertising\Support\AdvertisingToken;
use Modules\Category\Models\Category;
use Modules\Conversation\App\Models\Conversation;
use Modules\Conversation\App\Models\ConversationBlock;
use Modules\Listing\Models\Listing;
use Modules\User\App\Models\Profile;
use Modules\User\App\Models\User;
use Tests\TestCase;

final class PostClickActionsTest extends TestCase
{
    private User $seller;

    private User $buyer;

    private Listing $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seller = User::factory()->create(['status' => 'active']);
        $this->buyer = User::factory()->create(['status' => 'active']);
        $this->buyer->syncRoles([]);
        Profile::saveShopFor($this->seller, ['shop_enabled' => true, 'shop_name' => 'Public Shop Action', 'shop_description' => 'Shop introduction', 'shop_hours' => '9–18'], Profile::shopVersion($this->seller));
        $this->service = Listing::unguarded(fn () => Listing::create(['title' => 'Service Action Fixture', 'description' => 'A real service listing', 'slug' => (string) Str::uuid(), 'user_id' => $this->seller->id, 'category_id' => Category::where('slug', 'services')->firstOrFail()->id, 'status' => 'active', 'price' => 1000000, 'currency' => 'IRR']));
    }

    private function banner(string $kind = 'shop', ?string $url = null): Banner
    {
        $data = ['title' => 'Post click fixture', 'image' => 'banners/actions.jpg', 'alt' => 'Fixture', 'device' => 'both', 'is_active' => true, 'new_tab' => false, 'review_status' => 'approved'];
        $data += match ($kind) {
            'shop' => ['creative_format' => 'shop', 'shop_user_id' => $this->seller->id],
            'service' => ['creative_format' => 'service', 'service_listing_id' => $this->service->id],
            default => ['url' => $url, 'creative_format' => 'image'],
        };
        $banner = Banner::create($data);
        $banner->forceFill(['review_status' => 'approved', 'is_active' => true])->saveQuietly();
        BannerPlacement::where('key', 'home-top')->firstOrFail()->assignments()->create(['banner_id' => $banner->id, 'priority' => 1]);

        return $banner;
    }

    private function click(Banner $banner): void
    {
        $this->get('/')->assertOk();
        $token = AdvertisingToken::issue($banner, 'home-top', 'both', []);
        $this->withCookie(config('session.cookie'), request()->session()->getId());
        $response = $this->withCredentials()->postJson(route('advertising.events'), ['token' => $token, 'kind' => 'click', 'device' => 'mobile'])->assertNoContent();
        if ($cookie = $response->getCookie('advertising_attribution')) {
            $this->withCookie('advertising_attribution', $cookie->getValue());
        }
        Cookie::unqueue('advertising_attribution');
    }

    private function requestService(array $input = []): void
    {
        $this->withCredentials()->postJson(route('conversations.start', $this->service), array_replace(['service_request' => true, 'message' => 'لطفا زمان و هزینه را هماهنگ کنیم.'], $input))->assertSuccessful();
    }

    public function test_shop_visit_is_recorded_only_after_real_target_view_and_once_per_click(): void
    {
        $banner = $this->banner();
        $this->click($banner);
        $this->assertSame(0, AdvertisingEvent::where('kind', 'shop_view')->count());
        $click = AdvertisingEvent::where('kind', 'click')->firstOrFail();
        $this->assertSame($this->seller->id, $click->target_shop_id);
        $this->withCredentials()->get(route('sellers.show', $this->seller))->assertOk();
        $this->withCredentials()->get(route('sellers.show', $this->seller))->assertOk();
        $this->assertSame(1, AdvertisingEvent::where('kind', 'shop_view')->count());
        $event = AdvertisingEvent::where('kind', 'shop_view')->firstOrFail();
        $this->assertSame($click->id, $event->source_click_id);
        $this->assertSame(1, AdvertisingEvent::report(null, 'banner_id')[0]['shop_views']);
    }

    public function test_other_store_external_origin_and_disabled_store_do_not_count(): void
    {
        $other = User::factory()->create(['status' => 'active']);
        Profile::saveShopFor($other, ['shop_enabled' => true, 'shop_name' => 'Other shop'], Profile::shopVersion($other));
        $this->click($this->banner());
        $this->withCredentials()->get(route('sellers.show', $other))->assertOk();
        $this->assertSame(0, AdvertisingEvent::where('kind', 'shop_view')->count());
        Profile::detailsForUser($this->seller)->forceFill(['shop_enabled' => false])->save();
        $this->withCredentials()->get(route('sellers.show', $this->seller))->assertOk();
        $this->assertSame(0, AdvertisingEvent::where('kind', 'shop_view')->count());
        $this->assertNull(Profile::advertisingDestinationShopId('https://external.example/sellers/'.$other->id));
    }

    public function test_generic_internal_shop_link_is_eligible_and_click_snapshot_survives_pause(): void
    {
        $banner = $this->banner('image', route('sellers.show', $this->seller));
        $this->click($banner);
        $banner->update(['is_active' => false]);
        $this->withCredentials()->get(route('sellers.show', $this->seller))->assertOk();
        $this->assertSame(1, AdvertisingEvent::where('kind', 'shop_view')->count());
    }

    public function test_service_counts_successful_submission_separately_from_new_thread(): void
    {
        $this->actingAs($this->buyer);
        $this->click($this->banner('service'));
        $this->withCredentials()->get(route('listings.show', $this->service))->assertOk();
        $this->assertSame(0, AdvertisingEvent::where('kind', 'service')->count());
        $this->requestService();
        $this->requestService();
        $this->assertSame(1, AdvertisingEvent::where('kind', 'service')->count());
        $this->assertSame(1, AdvertisingEvent::where('kind', 'conversion')->count());
        $this->assertSame(1, AdvertisingEvent::report(null, 'banner_id')[0]['service_requests']);
        $this->assertSame(1, Conversation::where('listing_id', $this->service->id)->where('buyer_id', $this->buyer->id)->count());
    }

    public function test_request_in_existing_thread_counts_service_without_new_conversation(): void
    {
        Conversation::openForListingBuyer($this->service, $this->buyer->id);
        $this->actingAs($this->buyer);
        $this->click($this->banner('service'));
        $this->requestService();
        $this->assertSame(1, AdvertisingEvent::where('kind', 'service')->count());
        $this->assertSame(0, AdvertisingEvent::where('kind', 'conversion')->count());
    }

    public function test_failed_blocked_or_ordinary_messages_are_not_service_submissions(): void
    {
        $this->actingAs($this->buyer);
        $this->click($this->banner('service'));
        $this->withCredentials()->postJson(route('conversations.start', $this->service), ['service_request' => true, 'message' => '   '])->assertUnprocessable();
        ConversationBlock::setFor($this->seller, $this->buyer->id, true);
        $this->withCredentials()->postJson(route('conversations.start', $this->service), ['service_request' => true, 'message' => 'A valid message'])->assertUnprocessable();
        ConversationBlock::setFor($this->seller, $this->buyer->id, false);
        $this->requestService(['service_request' => false]);
        $this->assertSame(0, AdvertisingEvent::where('kind', 'service')->count());
    }

    public function test_expired_cookie_disabled_telemetry_and_public_payload_cannot_create_actions(): void
    {
        $this->actingAs($this->buyer);
        $this->click($this->banner('service'));
        $this->travel(25)->hours();
        $this->requestService();
        $this->assertSame(0, AdvertisingEvent::where('kind', 'service')->count());
        $this->travelBack();
        $this->click($this->banner('service'));
        $settings = app(OperationsSettings::class);
        $settings->telemetry_enabled = false;
        $settings->save();
        $this->requestService();
        $this->assertSame(0, AdvertisingEvent::where('kind', 'service')->count());
        foreach (['service', 'shop_view'] as $kind) {
            $this->withCredentials()->postJson(route('advertising.events'), ['token' => 'anything', 'kind' => $kind, 'device' => 'mobile'])->assertUnprocessable();
        }
    }

    public function test_analytics_failure_does_not_block_successful_service_message(): void
    {
        $this->actingAs($this->buyer);
        $this->click($this->banner('service'));
        app()->bind(OperationsSettings::class, fn () => throw new \RuntimeException('Metrics unavailable'));
        $this->requestService();
        $this->assertNotNull(Conversation::buyerListingConversationId($this->service->id, $this->buyer->id));
        $this->assertSame(0, AdvertisingEvent::where('kind', 'service')->count());
    }

    public function test_latest_eligible_click_controls_action_target_and_store_snapshot_changes_fail_closed(): void
    {
        $this->actingAs($this->buyer);
        $shop = $this->banner();
        $this->click($shop);
        $this->click($this->banner('service'));
        $this->withCredentials()->get(route('sellers.show', $this->seller))->assertOk();
        $this->assertSame(0, AdvertisingEvent::where('kind', 'shop_view')->count());
        $this->requestService();
        $this->assertSame(1, AdvertisingEvent::where('kind', 'service')->count());
        $this->get('/');
        $token = AdvertisingToken::issue($shop, 'home-top', 'both', []);
        $shop->forceFill(['shop_snapshot' => ['name' => 'Changed', 'description' => '', 'hours' => '']])->saveQuietly();
        $this->withCookie(config('session.cookie'), request()->session()->getId());
        $this->withCredentials()->postJson(route('advertising.events'), ['token' => $token, 'kind' => 'click', 'device' => 'mobile'])->assertNoContent();
        $this->assertSame(2, AdvertisingEvent::where('kind', 'click')->count());
    }
}
