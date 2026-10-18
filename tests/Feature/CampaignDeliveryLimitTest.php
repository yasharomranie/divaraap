<?php

declare(strict_types=1);

namespace Tests\Feature;

use Filament\Facades\Filament;
use Livewire\Livewire;
use Modules\Admin\App\Settings\OperationsSettings;
use Modules\Advertising\Filament\Admin\Resources\CampaignResource\Pages\CreateCampaign;
use Modules\Advertising\Models\AdvertisingEvent;
use Modules\Advertising\Models\Banner;
use Modules\Advertising\Models\BannerPlacement;
use Modules\Advertising\Models\Campaign;
use Modules\Advertising\Support\AdvertisingToken;
use Modules\Advertising\Support\BannerRenderer;
use Modules\User\App\Models\User;
use Tests\TestCase;

final class CampaignDeliveryLimitTest extends TestCase
{
    private function setupCampaign(array $attributes = []): array
    {
        $campaign = Campaign::create(array_replace(['title' => 'Limited campaign', 'is_active' => true], $attributes));
        $banner = Banner::create(['title' => 'Limited creative', 'image' => 'banners/limit.jpg', 'alt' => 'Banner', 'is_active' => true, 'device' => 'both', 'url' => 'https://example.com', 'campaign_id' => $campaign->id]);
        BannerPlacement::where('key', 'home-top')->firstOrFail()->assignments()->create(['banner_id' => $banner->id, 'priority' => 1]);
        $this->get('/')->assertSuccessful();
        $this->withCookie(config('session.cookie'), request()->session()->getId());

        return [$campaign, $banner];
    }

    private function send(string $token, string $kind = 'impression'): void
    {
        $this->travel(2)->seconds();
        $this->withCredentials()->postJson(route('advertising.events'), ['token' => $token, 'kind' => $kind, 'device' => 'mobile'])->assertNoContent();
    }

    public function test_impression_limit_stops_new_delivery_without_discarding_already_served_events(): void
    {
        [$campaign, $banner] = $this->setupCampaign(['impression_limit' => 1]);
        $first = AdvertisingToken::issue($banner, 'home-top', 'both', []);
        $second = AdvertisingToken::issue($banner, 'home-top', 'both', []);
        $this->send($first);
        $this->send($first);
        $this->assertSame(1, $campaign->fresh()->recorded_impressions);
        $this->assertSame('impression_limit', $campaign->fresh()->deliveryState());
        $this->assertSame([], (new BannerRenderer)->at('home-top'));
        $this->send($second);
        $this->assertSame(2, AdvertisingEvent::where('kind', 'impression')->count());
        $this->assertSame(2, $campaign->fresh()->recorded_impressions);
        $campaign->update(['impression_limit' => 3]);
        $this->assertSame('running', $campaign->fresh()->deliveryState());
        $this->assertSame($banner->id, (new BannerRenderer)->at('home-top')['both']->id);
    }

    public function test_click_limit_is_shared_and_counts_only_unique_clicks(): void
    {
        [$campaign, $banner] = $this->setupCampaign(['click_limit' => 1]);
        $token = AdvertisingToken::issue($banner, 'home-top', 'both', []);
        $this->send($token, 'click');
        $this->send($token, 'click');
        $this->assertSame(1, $campaign->fresh()->recorded_clicks);
        $this->assertSame(0, $campaign->fresh()->recorded_impressions);
        $this->assertSame('click_limit', $campaign->fresh()->deliveryState());
        $this->assertSame([], (new BannerRenderer)->at('home-top'));
    }

    public function test_cost_is_an_atomic_snapshot_of_the_served_prices_and_not_planned_budget(): void
    {
        [$campaign, $banner] = $this->setupCampaign(['cost_limit' => 100, 'impression_cost' => 30, 'click_cost' => 40, 'planned_budget' => 1]);
        $token = AdvertisingToken::issue($banner, 'home-top', 'both', []);
        $campaign->update(['impression_cost' => 60]);
        $this->send($token);
        $this->send($token, 'click');
        $this->send($token, 'click');
        $this->assertSame(70, $campaign->fresh()->recorded_cost);
        $this->assertSame('cost_limit', $campaign->fresh()->deliveryState());
        $this->assertSame([], (new BannerRenderer)->at('home-top'));
        $campaign->update(['cost_limit' => 200]);
        $this->assertSame('running', $campaign->fresh()->deliveryState());
    }

    public function test_counter_survives_event_retention_and_independent_banners_are_not_stopped(): void
    {
        [$campaign, $banner] = $this->setupCampaign(['impression_limit' => 1]);
        $this->send(AdvertisingToken::issue($banner, 'home-top', 'both', []));
        AdvertisingEvent::query()->forceDelete();
        $this->assertSame('impression_limit', $campaign->fresh()->deliveryState());
        $banner->update(['campaign_id' => null]);
        $this->assertSame($banner->id, (new BannerRenderer)->at('home-top')['both']->id);
    }

    public function test_disabled_telemetry_and_schedule_remain_authoritative(): void
    {
        [$campaign, $banner] = $this->setupCampaign(['click_limit' => 10]);
        $settings = app(OperationsSettings::class);
        $settings->telemetry_enabled = false;
        $settings->save();
        $this->assertSame('telemetry_off', $campaign->deliveryState());
        $this->assertFalse($campaign->eligible([]));
        $campaign->update(['is_active' => false]);
        $this->assertSame('paused', $campaign->deliveryState());
        $campaign->update(['is_active' => true, 'starts_at' => now()->addDay()]);
        $this->assertSame('scheduled', $campaign->deliveryState());
        $campaign->update(['starts_at' => null, 'ends_at' => now()]);
        $this->assertSame('ended', $campaign->deliveryState());
        $this->assertFalse($campaign->eligible([], forDelivery: false));
    }

    public function test_admin_can_configure_limits_and_reject_zero_negative_and_excessive_costs(): void
    {
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $this->actingAs(User::where('email', 'a@a.com')->firstOrFail());
        Livewire::test(CreateCampaign::class)->fillForm(['title' => 'Configured limits', 'goal' => 'awareness', 'impression_limit' => 100, 'click_limit' => 10, 'cost_limit' => 10000, 'impression_cost' => 2, 'click_cost' => 20])->call('create')->assertHasNoFormErrors();
        $campaign = Campaign::where('title', 'Configured limits')->firstOrFail();
        $this->assertSame(100, $campaign->impression_limit);
        $this->assertSame(0, $campaign->recorded_cost);
        Livewire::test(CreateCampaign::class)->fillForm(['title' => 'Invalid limits', 'goal' => 'awareness', 'impression_limit' => 0, 'click_limit' => -1, 'cost_limit' => 0, 'impression_cost' => -1, 'click_cost' => 1000000001])->call('create')->assertHasFormErrors(['impression_limit', 'click_limit', 'cost_limit', 'impression_cost', 'click_cost']);
    }
}
