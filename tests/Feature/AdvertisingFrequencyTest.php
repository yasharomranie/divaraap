<?php

declare(strict_types=1);

namespace Tests\Feature;

use Filament\Facades\Filament;
use Illuminate\Support\Facades\Cookie;
use Livewire\Livewire;
use Modules\Admin\App\Settings\OperationsSettings;
use Modules\Advertising\Filament\Admin\Resources\CampaignResource\Pages\CreateCampaign;
use Modules\Advertising\Models\AdvertisingEvent;
use Modules\Advertising\Models\Banner;
use Modules\Advertising\Models\BannerPlacement;
use Modules\Advertising\Models\Campaign;
use Modules\Advertising\Models\FrequencyCount;
use Modules\Advertising\Support\BannerRenderer;
use Modules\User\App\Models\User;
use Tests\TestCase;

final class AdvertisingFrequencyTest extends TestCase
{
    private function banner(array $attributes = [], string $placement = 'home-top', int $priority = 1): Banner
    {
        $banner = Banner::create(array_replace(['title' => 'Frequency banner', 'image' => 'banners/frequency.jpg', 'alt' => 'Banner', 'device' => 'both', 'is_active' => true], $attributes));
        BannerPlacement::where('key', $placement)->firstOrFail()->assignments()->create(['banner_id' => $banner->id, 'priority' => $priority]);

        return $banner;
    }

    private function render(): string
    {
        app()->forgetInstance(BannerRenderer::class);
        $response = $this->withCredentials()->get('/')->assertSuccessful();
        $this->withCookie(config('session.cookie'), request()->session()->getId());
        if ($cookie = $response->getCookie('advertising_frequency')) {
            $this->withCookie('advertising_frequency', $cookie->getValue());
        }
        Cookie::unqueue('advertising_frequency');

        return $response->getContent();
    }

    private function token(string $html, Banner $banner): string
    {
        $this->assertSame(1, preg_match('/<aside[^>]*data-banner="'.$banner->id.'"[^>]*data-event-token="([^"]+)"/', $html, $matches));

        return html_entity_decode($matches[1], ENT_QUOTES);
    }

    private function impression(string $token): void
    {
        $this->travel(2)->seconds();
        $this->withCredentials()->postJson(route('advertising.events'), ['token' => $token, 'kind' => 'impression', 'device' => 'mobile'])->assertNoContent();
    }

    public function test_viewable_impression_reaches_cap_without_counting_renders_or_replays(): void
    {
        $first = $this->banner(['daily_frequency_cap' => 1]);
        $fallback = $this->banner(priority: 2);
        $html = $this->render();
        $this->assertSame(0, FrequencyCount::count());
        $this->assertStringContainsString('data-banner="'.$first->id.'"', $this->render());
        $token = $this->token($html, $first);
        $this->impression($token);
        $this->impression($token);
        $this->assertSame(1, (int) FrequencyCount::firstOrFail()->impressions);
        $this->assertSame(1, AdvertisingEvent::where('kind', 'impression')->count());
        $html = $this->render();
        $this->assertStringNotContainsString('data-banner="'.$first->id.'"', $html);
        $this->assertStringContainsString('data-banner="'.$fallback->id.'"', $html);
    }

    public function test_campaign_limit_is_shared_across_banners_and_placements(): void
    {
        $campaign = Campaign::create(['title' => 'Shared', 'is_active' => true, 'daily_frequency_cap' => 1]);
        $first = $this->banner(['campaign_id' => $campaign->id]);
        $second = $this->banner(['campaign_id' => $campaign->id], 'home-feed');
        BannerPlacement::where('key', 'home-feed')->update(['first_after' => 1]);
        $html = $this->render();
        $this->assertStringContainsString('data-banner="'.$second->id.'"', $html);
        $this->impression($this->token($html, $first));
        $html = $this->render();
        $this->assertStringNotContainsString('data-banner="'.$first->id.'"', $html);
        $this->assertStringNotContainsString('data-banner="'.$second->id.'"', $html);
        $this->assertSame('campaign', FrequencyCount::firstOrFail()->scope);
    }

    public function test_banner_and_campaign_caps_both_apply_and_next_day_resets(): void
    {
        $campaign = Campaign::create(['title' => 'Shared', 'is_active' => true, 'daily_frequency_cap' => 3]);
        $banner = $this->banner(['campaign_id' => $campaign->id, 'daily_frequency_cap' => 1]);
        $this->impression($this->token($this->render(), $banner));
        $this->assertSame(2, FrequencyCount::count());
        $this->assertStringNotContainsString('data-banner="'.$banner->id.'"', $this->render());
        $this->travel(1)->days();
        $this->assertStringContainsString('data-banner="'.$banner->id.'"', $this->render());
    }

    public function test_other_browser_has_its_own_counter_and_cannot_replay_another_browser_token(): void
    {
        $banner = $this->banner(['daily_frequency_cap' => 1]);
        $token = $this->token($this->render(), $banner);
        $this->impression($token);
        $this->withCookie('advertising_frequency', '');
        $html = $this->render();
        $this->assertStringContainsString('data-banner="'.$banner->id.'"', $html);
        $this->impression($token);
        $this->assertSame(1, AdvertisingEvent::count());
        $this->impression($this->token($html, $banner));
        $this->assertSame(2, AdvertisingEvent::count());
        $this->assertSame(2, FrequencyCount::count());
    }

    public function test_telemetry_off_hides_capped_ads_and_leaves_uncapped_ads_available(): void
    {
        $capped = $this->banner(['daily_frequency_cap' => 1]);
        $uncapped = $this->banner(priority: 2);
        $settings = app(OperationsSettings::class);
        $settings->telemetry_enabled = false;
        $settings->save();
        $html = $this->render();
        $this->assertStringNotContainsString('data-banner="'.$capped->id.'"', $html);
        $this->assertStringContainsString('data-banner="'.$uncapped->id.'"', $html);
        $this->assertSame(0, FrequencyCount::count());
    }

    public function test_admin_can_set_campaign_cap_and_reject_invalid_limits(): void
    {
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $this->actingAs(User::where('email', 'a@a.com')->firstOrFail());
        Livewire::test(CreateCampaign::class)->fillForm(['title' => 'Limited campaign', 'goal' => 'awareness', 'daily_frequency_cap' => 3])->call('create')->assertHasNoFormErrors();
        $this->assertSame(3, Campaign::where('title', 'Limited campaign')->firstOrFail()->daily_frequency_cap);
        foreach ([0, -1, 10001] as $limit) {
            Livewire::test(CreateCampaign::class)->fillForm(['title' => 'Invalid limit', 'goal' => 'awareness', 'daily_frequency_cap' => $limit])->call('create')->assertHasFormErrors(['daily_frequency_cap']);
        }
    }
}
