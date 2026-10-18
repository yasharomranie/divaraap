<?php

declare(strict_types=1);

namespace Tests\Feature;

use Filament\Facades\Filament;
use Livewire\Livewire;
use Modules\Advertising\Filament\Admin\Pages\AdvertisingPreview;
use Modules\Advertising\Models\AdvertisingEvent;
use Modules\Advertising\Models\Banner;
use Modules\Advertising\Models\Campaign;
use Modules\Advertising\Models\FrequencyCount;
use Modules\User\App\Models\User;
use Tests\TestCase;

final class AdvertisingPreviewTest extends TestCase
{
    private function banner(): Banner
    {
        return Banner::create(['title' => 'Preview draft', 'image' => 'banners/preview-desktop.jpg', 'mobile_image' => 'banners/preview-mobile.jpg', 'alt' => 'Preview image', 'url' => 'https://example.com', 'is_active' => false, 'review_status' => 'pending', 'device' => 'both']);
    }

    private function admin(): void
    {
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $this->actingAs(User::where('email', 'a@a.com')->firstOrFail());
    }

    public function test_preview_displays_saved_draft_without_tracking_links_or_client_scripts(): void
    {
        $banner = $this->banner();
        $this->admin();
        $response = $this->get(route('advertising.preview-render', ['banner' => $banner->id, 'device' => 'mobile', 'placement' => 'home-feed']));
        $response->assertSuccessful()->assertSee('preview-mobile.jpg', false)->assertSee('preview-desktop.jpg', false)->assertSee('data-placement="home-feed"', false)
            ->assertDontSee('data-event-token', false)->assertDontSee('data-behavior="advertising-metrics"', false)->assertDontSee('https://example.com', false)->assertDontSee('<script', false);
        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
        $response->assertHeader('X-Frame-Options', 'SAMEORIGIN')->assertHeader('X-Robots-Tag', 'noindex, nofollow');
        $this->assertSame(0, AdvertisingEvent::count());
        $this->assertSame(0, FrequencyCount::count());
    }

    public function test_preview_does_not_reserve_delivery_or_change_spend_even_when_campaign_stopped(): void
    {
        $campaign = Campaign::create(['title' => 'Stopped campaign', 'is_active' => false, 'impression_limit' => 1, 'recorded_impressions' => 1, 'recorded_cost' => 55]);
        $banner = $this->banner();
        $banner->update(['campaign_id' => $campaign->id, 'daily_frequency_cap' => 1]);
        $this->admin();
        foreach (['mobile', 'desktop'] as $device) {
            $this->get(route('advertising.preview-render', ['banner' => $banner->id, 'device' => $device]))->assertSuccessful()->assertSee('Preview image');
        }
        $this->assertSame(1, $campaign->fresh()->recorded_impressions);
        $this->assertSame(55, $campaign->fresh()->recorded_cost);
        $this->assertSame(0, AdvertisingEvent::count());
        $this->assertSame(0, FrequencyCount::count());
    }

    public function test_device_restrictions_and_input_validation_apply(): void
    {
        $banner = $this->banner();
        $banner->update(['device' => 'desktop']);
        $this->admin();
        $this->get(route('advertising.preview-render', ['banner' => $banner->id, 'device' => 'mobile']))->assertSuccessful()->assertSee(__('advertising::preview.device_mismatch'))->assertDontSee('data-banner=', false);
        $this->getJson(route('advertising.preview-render', ['banner' => $banner->id, 'device' => 'tablet']))->assertUnprocessable();
        $this->getJson(route('advertising.preview-render', ['banner' => $banner->id, 'placement' => '../../private']))->assertUnprocessable();
        $banner->delete();
        $this->get(route('advertising.preview-render', ['banner' => $banner->id]))->assertNotFound();
    }

    public function test_preview_page_selects_saved_banner_and_preserves_mobile_desktop_frame_widths(): void
    {
        $banner = $this->banner();
        $this->admin();
        Livewire::test(AdvertisingPreview::class)->set('bannerId', (string) $banner->id)->assertSee('width="390"', false)->assertSee('sandbox="allow-same-origin"', false)
            ->set('device', 'desktop')->assertSee('width="1200"', false)->set('placement', 'page-bottom')->assertSee('page-bottom', false);
    }

    public function test_only_active_advertising_staff_can_access_page_and_frame(): void
    {
        $banner = $this->banner();
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $this->get(route('advertising.preview-render', ['banner' => $banner->id]))->assertRedirect();
        $user = User::factory()->create(['status' => 'active']);
        $this->actingAs($user);
        $this->get(route('advertising.preview-render', ['banner' => $banner->id]))->assertForbidden();
        $this->get(AdvertisingPreview::getUrl())->assertForbidden();
        $user->assignRole('advertising-manager');
        $this->get(route('advertising.preview-render', ['banner' => $banner->id]))->assertSuccessful();
        $this->get(AdvertisingPreview::getUrl())->assertSuccessful();
        $user->update(['status' => 'suspended']);
        $this->get(route('advertising.preview-render', ['banner' => $banner->id]))->assertForbidden();
    }

    public function test_untrusted_alt_is_escaped_in_shared_creative(): void
    {
        $banner = $this->banner();
        $banner->update(['alt' => '"><script>alert(1)</script>']);
        $this->admin();
        $this->get(route('advertising.preview-render', ['banner' => $banner->id]))->assertSuccessful()->assertDontSee('<script>', false)->assertSee('&lt;script&gt;', false);
    }
}
