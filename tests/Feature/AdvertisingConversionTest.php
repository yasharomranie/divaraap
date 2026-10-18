<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Support\Facades\Cookie;
use Modules\Admin\App\Settings\OperationsSettings;
use Modules\Advertising\Models\AdvertisingEvent;
use Modules\Advertising\Models\Banner;
use Modules\Advertising\Models\BannerPlacement;
use Modules\Advertising\Support\AdvertisingToken;
use Modules\Conversation\App\Models\Conversation;
use Modules\Listing\Models\Listing;
use Modules\User\App\Models\User;
use Tests\TestCase;

final class AdvertisingConversionTest extends TestCase
{
    private function click(?Listing $listing = null, ?string $url = null): Banner
    {
        $banner = Banner::create(['title' => 'Conversation campaign', 'image' => 'banners/conversion.jpg', 'alt' => 'Banner', 'url' => $url ?? route('listings.show', $listing), 'device' => 'both', 'is_active' => true]);
        $banner->forceFill(['review_status' => 'approved', 'is_active' => true])->saveQuietly();
        BannerPlacement::where('key', 'home-top')->firstOrFail()->assignments()->create(['banner_id' => $banner->id, 'priority' => 1]);
        $this->get('/')->assertSuccessful();
        $token = AdvertisingToken::issue($banner, 'home-top', 'both', []);
        $this->withCookie(config('session.cookie'), request()->session()->getId());
        $response = $this->withCredentials()->postJson(route('advertising.events'), ['token' => $token, 'kind' => 'click', 'device' => 'mobile'])->assertNoContent();
        if ($cookie = $response->getCookie('advertising_attribution')) {
            $this->withCookie('advertising_attribution', $cookie->getValue());
        }

        Cookie::unqueue('advertising_attribution');

        return $banner;
    }

    private function listing(): Listing
    {
        return Listing::where('status', 'active')->whereNotNull('user_id')->firstOrFail();
    }

    private function start(Listing $listing): void
    {
        $this->withCredentials()->postJson(route('conversations.start', $listing), ['message' => 'Is this available?'])->assertSuccessful();
    }

    public function test_real_new_conversation_is_attributed_once_to_its_click(): void
    {
        $listing = $this->listing();
        $this->actingAs(User::factory()->create());
        $banner = $this->click($listing);
        $this->start($listing);
        $this->start($listing);
        $this->assertSame(1, AdvertisingEvent::where('kind', 'conversion')->count());
        $conversion = AdvertisingEvent::where('kind', 'conversion')->firstOrFail();
        $this->assertSame($banner->id, $conversion->banner_id);
        $this->assertSame($listing->id, $conversion->target_listing_id);
        $this->assertSame(AdvertisingEvent::where('kind', 'click')->firstOrFail()->id, $conversion->source_click_id);
        $this->assertSame(1, AdvertisingEvent::report(null, 'banner_id')[0]['conversations']);
    }

    public function test_existing_conversation_is_not_a_new_conversion(): void
    {
        $listing = $this->listing();
        $buyer = User::factory()->create();
        Conversation::openForListingBuyer($listing, $buyer->id);
        $this->actingAs($buyer);
        $this->click($listing);
        $this->start($listing);
        $this->assertSame(0, AdvertisingEvent::where('kind', 'conversion')->count());
    }

    public function test_other_listing_and_external_links_do_not_receive_attribution(): void
    {
        $listing = $this->listing();
        $other = Listing::where('status', 'active')->where('id', '!=', $listing->id)->whereNotNull('user_id')->firstOrFail();
        $this->actingAs(User::factory()->create());
        $this->click($other);
        $this->start($listing);
        $this->assertSame(0, AdvertisingEvent::where('kind', 'conversion')->count());
        $this->withCredentials()->get('/');
        $this->withCookie('advertising_attribution', '');
        $this->click(url: 'https://example.com/listings/'.$other->id);
        $this->start($other);
        $this->assertSame(0, AdvertisingEvent::where('kind', 'conversion')->count());
    }

    public function test_expired_click_and_disabled_telemetry_do_not_count(): void
    {
        $listing = $this->listing();
        $this->actingAs(User::factory()->create());
        $this->click($listing);
        $this->travel(25)->hours();
        $this->start($listing);
        $this->assertSame(0, AdvertisingEvent::where('kind', 'conversion')->count());
        $this->actingAs(User::factory()->create());
        $this->click($listing);
        $settings = app(OperationsSettings::class);
        $settings->telemetry_enabled = false;
        $settings->save();
        $this->start($listing);
        $this->assertSame(0, AdvertisingEvent::where('kind', 'conversion')->count());
    }

    public function test_analytics_failure_does_not_block_conversation_creation(): void
    {
        $listing = $this->listing();
        $buyer = User::factory()->create();
        $this->actingAs($buyer);
        $this->click($listing);
        app()->bind(OperationsSettings::class, fn () => throw new \RuntimeException('Analytics unavailable'));
        $this->start($listing);
        $this->assertNotNull(Conversation::buyerListingConversationId($listing->id, $buyer->id));
        $this->assertSame(0, AdvertisingEvent::where('kind', 'conversion')->count());
    }

    public function test_newer_click_wins_and_public_conversion_payload_is_forbidden(): void
    {
        $listing = $this->listing();
        $this->actingAs(User::factory()->create());
        $first = $this->click($listing);
        $second = $this->click($listing);
        $this->start($listing);
        $conversion = AdvertisingEvent::where('kind', 'conversion')->firstOrFail();
        $this->assertSame($second->id, $conversion->banner_id);
        $this->assertNotSame($first->id, $conversion->banner_id);
        $this->withCredentials()->postJson(route('advertising.events'), ['token' => 'anything', 'kind' => 'conversion', 'device' => 'mobile'])->assertUnprocessable();
    }
}
