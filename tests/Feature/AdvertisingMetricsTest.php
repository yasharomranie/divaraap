<?php

declare(strict_types=1);

namespace Tests\Feature;

use Filament\Facades\Filament;
use Livewire\Livewire;
use Modules\Admin\App\Settings\OperationsSettings;
use Modules\Advertising\Filament\Admin\Pages\AdvertisingStatistics;
use Modules\Advertising\Models\AdvertisingEvent;
use Modules\Advertising\Models\Banner;
use Modules\Advertising\Models\BannerPlacement;
use Modules\Advertising\Models\Campaign;
use Modules\Advertising\Support\AdvertisingToken;
use Modules\User\App\Models\User;
use Tests\TestCase;

final class AdvertisingMetricsTest extends TestCase
{
    private function exposure(array $attributes = [], string $device = 'both'): array
    {
        $banner = Banner::create(array_replace(['title' => 'Tracked banner', 'image' => 'banners/tracked.jpg', 'alt' => 'Banner', 'url' => 'https://example.com', 'device' => 'both', 'is_active' => true], $attributes));
        BannerPlacement::where('key', 'home-top')->firstOrFail()->assignments()->create(['banner_id' => $banner->id, 'priority' => 1]);
        $this->get('/')->assertSuccessful();
        $token = AdvertisingToken::issue($banner, 'home-top', $device, []);
        $this->withCookie(config('session.cookie'), request()->session()->getId());
        $this->assertNotNull(AdvertisingToken::read($token));

        return [$banner, $token];
    }

    private function send(string $token, string $kind = 'impression', string $device = 'mobile'): void
    {
        $this->withCredentials()->postJson(route('advertising.events'), compact('token', 'kind', 'device'))->assertNoContent();
    }

    public function test_render_does_not_count_and_replays_are_deduplicated_across_devices(): void
    {
        [$banner, $token] = $this->exposure();
        $this->assertSame(0, AdvertisingEvent::count());
        $this->send($token);
        $this->assertSame(0, AdvertisingEvent::count());
        $this->travel(2)->seconds();
        $this->assertTrue(app(OperationsSettings::class)->telemetry_enabled);
        $this->send($token);
        $this->send($token);
        $this->send($token, device: 'desktop');
        $this->send($token, 'click');
        $this->send($token, 'click');
        $this->assertSame(2, AdvertisingEvent::count());
        $this->assertSame($banner->id, AdvertisingEvent::firstOrFail()->banner_id);
        $this->assertSame(100.0, AdvertisingEvent::report(null, 'banner_id')[0]['ctr']);
    }

    public function test_expired_forged_other_session_and_wrong_device_tokens_are_ignored(): void
    {
        [, $token] = $this->exposure(device: 'mobile');
        $this->travel(2)->seconds();
        $this->send('forged');
        $this->send($token, device: 'desktop');
        $session = request()->session();
        $original = $session->getId();
        $session->setId(str_repeat('z', 40));
        $this->withCookie(config('session.cookie'), str_repeat('z', 40));
        $this->send($token);
        $session->setId($original);
        $this->withCookie(config('session.cookie'), $original);
        $this->travel(3)->hours();
        $this->send($token);
        $this->assertSame(0, AdvertisingEvent::count());
    }

    public function test_pausing_campaign_removing_assignment_and_disabling_telemetry_stop_tracking(): void
    {
        $campaign = Campaign::create(['title' => 'Paused', 'is_active' => true]);
        [$banner, $token] = $this->exposure(['campaign_id' => $campaign->id]);
        $this->travel(2)->seconds();
        $campaign->update(['is_active' => false]);
        $this->send($token);
        $campaign->update(['is_active' => true]);
        $banner->assignments()->delete();
        $this->send($token);
        $this->assertSame(0, AdvertisingEvent::count());
        [, $second] = $this->exposure();
        $settings = app(OperationsSettings::class);
        $settings->telemetry_enabled = false;
        $settings->save();
        $this->send($second, 'click');
        $this->assertSame(0, AdvertisingEvent::count());
    }

    public function test_campaign_attribution_is_a_snapshot_and_reports_filter_and_group(): void
    {
        $campaign = Campaign::create(['title' => 'Campaign A', 'is_active' => true]);
        [$banner, $token] = $this->exposure(['campaign_id' => $campaign->id]);
        $this->travel(2)->seconds();
        $this->send($token);
        $this->send($token, 'click');
        $banner->update(['campaign_id' => null]);
        $this->send($token, 'click');
        [, $independent] = $this->exposure();
        $this->send($independent, 'click', 'desktop');
        $this->assertCount(2, AdvertisingEvent::report(null, 'banner_id'));
        $this->assertNull(AdvertisingEvent::report(null, 'banner_id')[0]['ctr']);
        $rows = AdvertisingEvent::report($campaign->id, 'placement');
        $this->assertSame('home-top', $rows[0]['label']);
        $this->assertSame(1, $rows[0]['impressions']);
        $this->assertSame(1, $rows[0]['clicks']);
        $this->assertSame('desktop', AdvertisingEvent::report(null, 'device')[1]['label']);
        $this->assertCount(1, AdvertisingEvent::report(null, 'event_date'));
    }

    public function test_click_without_destination_and_invalid_event_payload_are_rejected(): void
    {
        [, $token] = $this->exposure(['url' => 'javascript:alert(1)']);
        $this->send($token, 'click');
        $this->assertSame(0, AdvertisingEvent::count());
        $this->postJson(route('advertising.events'), ['token' => $token, 'kind' => 'purchase', 'device' => 'mobile'])->assertUnprocessable();
    }

    public function test_old_events_are_pruned_and_disabled_placements_are_not_counted(): void
    {
        [$banner, $token] = $this->exposure();
        AdvertisingEvent::create(['banner_id' => $banner->id, 'placement' => 'home-top', 'device' => 'mobile', 'kind' => 'impression', 'receipt' => str_repeat('a', 64), 'event_date' => now()->subDays(91)->toDateString(), 'created_at' => now()->subDays(91)]);
        $this->travel(2)->seconds();
        $this->send($token);
        $this->assertSame(1, AdvertisingEvent::count());
        $this->assertFalse(AdvertisingEvent::where('receipt', str_repeat('a', 64))->exists());
        BannerPlacement::where('key', 'home-top')->update(['is_active' => false]);
        $this->send($token, 'click');
        $this->assertSame(1, AdvertisingEvent::count());
    }

    public function test_statistics_page_is_restricted_to_active_advertising_staff(): void
    {
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $user = User::factory()->create(['status' => 'active']);
        $this->actingAs($user);
        $this->get(AdvertisingStatistics::getUrl())->assertForbidden();
        $user->assignRole('advertising-manager');
        $this->get(AdvertisingStatistics::getUrl())->assertSuccessful();
        Livewire::test(AdvertisingStatistics::class)->assertSee(__('advertising::metrics.empty'))->set('dimension', 'device')->assertSuccessful();
        $user->update(['status' => 'suspended']);
        $this->assertFalse(AdvertisingStatistics::canAccess());
    }
}
