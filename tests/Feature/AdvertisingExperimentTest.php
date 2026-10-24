<?php

declare(strict_types=1);

namespace Tests\Feature;

use Filament\Facades\Filament;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Modules\Advertising\Filament\Admin\Resources\AdvertisingExperimentResource;
use Modules\Advertising\Filament\Admin\Resources\AdvertisingExperimentResource\Pages\CreateExperiment;
use Modules\Advertising\Filament\Admin\Resources\AdvertisingExperimentResource\Pages\ListExperiments;
use Modules\Advertising\Models\AdvertisingEvent;
use Modules\Advertising\Models\AdvertisingExperiment;
use Modules\Advertising\Models\Banner;
use Modules\Advertising\Models\BannerPlacement;
use Modules\Advertising\Models\Campaign;
use Modules\Advertising\Support\AdvertisingToken;
use Modules\Advertising\Support\BannerRenderer;
use Modules\User\App\Models\User;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

final class AdvertisingExperimentTest extends TestCase
{
    private BannerPlacement $placement;

    private Banner $a;

    private Banner $b;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs(User::where('email', 'a@a.com')->firstOrFail());
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        Storage::fake('public');
        $image = UploadedFile::fake()->image('experiment.jpg');
        Storage::disk('public')->put('banners/experiment.jpg', file_get_contents($image->getRealPath()));
        $this->placement = BannerPlacement::where('key', 'home-top')->firstOrFail();
        $this->placement->update(['is_active' => true, 'device' => 'both']);
        $this->a = $this->banner('Design A');
        $this->b = $this->banner('Design B');
    }

    private function banner(string $title): Banner
    {
        $banner = Banner::create(['title' => $title, 'alt' => $title, 'image' => 'banners/experiment.jpg', 'url' => 'https://example.com/listings', 'device' => 'both', 'is_active' => true, 'review_status' => 'approved']);
        $this->placement->assignments()->create(['banner_id' => $banner->id, 'priority' => 1]);

        return $banner;
    }

    private function experiment(array $data = []): AdvertisingExperiment
    {
        return AdvertisingExperiment::create(array_replace(['name' => 'Creative comparison', 'placement_id' => $this->placement->id, 'banner_a_id' => $this->a->id, 'banner_b_id' => $this->b->id, 'duration_days' => 7], $data));
    }

    private function invalid(callable $action): void
    {
        try {
            $action();
            $this->fail('Expected validation failure.');
        } catch (ValidationException) {
            $this->assertTrue(true);
        }
    }

    public function test_pair_rules_and_immutable_history(): void
    {
        $this->invalid(fn () => $this->experiment(['banner_b_id' => $this->a->id]));
        $this->invalid(fn () => $this->experiment(['duration_days' => 31]));
        $this->b->update(['device' => 'mobile']);
        $this->invalid(fn () => $this->experiment());
        $this->b->update(['device' => 'both', 'url' => 'https://example.com/different']);
        $this->invalid(fn () => $this->experiment());
        $this->b->update(['url' => 'https://example.com/listings', 'daily_frequency_cap' => 2]);
        $this->invalid(fn () => $this->experiment());
        $this->b->update(['daily_frequency_cap' => null]);
        $experiment = $this->experiment();
        $this->invalid(fn () => $experiment->update(['name' => 'Changed']));
        $this->invalid(fn () => $experiment->delete());
        $this->assertSame('Creative comparison', $experiment->fresh()->name);
    }

    public function test_same_campaign_assignment_and_image_availability_are_required(): void
    {
        $campaign = Campaign::create(['title' => 'One campaign', 'is_active' => true]);
        $this->b->update(['campaign_id' => $campaign->id]);
        $this->invalid(fn () => $this->experiment());
        $this->b->update(['campaign_id' => null]);
        $assignment = $this->placement->assignments()->where('banner_id', $this->b->id)->firstOrFail();
        $assignment->delete();
        $this->invalid(fn () => $this->experiment());
        $assignment->restore();
        Storage::disk('public')->delete('banners/experiment.jpg');
        $this->invalid(fn () => $this->experiment());
    }

    public function test_start_stop_exclusion_replay_and_expiry(): void
    {
        $first = $this->experiment();
        $second = $this->experiment();
        $first->start();
        $started = $first->started_at->toISOString();
        $first->start();
        $this->assertSame($started, $first->started_at->toISOString());
        $this->assertEquals($first->started_at->copy()->addDays(7), $first->ends_at);
        $this->invalid(fn () => $second->start());
        $first->stop();
        $first->stop();
        $this->invalid(fn () => $first->start());
        $second->start();
        $this->travel(8)->days();
        $this->assertSame(__('advertising::experiments.ended'), $second->fresh()->statusLabel());
        $this->assertNull(AdvertisingExperiment::delivery($this->placement, []));
        $third = $this->experiment();
        $third->start();
        $this->assertSame('running', $third->state);
    }

    public function test_stable_session_split_is_balanced_without_exact_quota(): void
    {
        $counts = ['A' => 0, 'B' => 0];
        $stable = true;
        for ($i = 0; $i < 2000; $i++) {
            $arm = AdvertisingExperiment::arm(17, 'session-'.$i);
            $stable = $stable && $arm === AdvertisingExperiment::arm(17, 'session-'.$i);
            $counts[$arm]++;
        }
        $this->assertTrue($stable);
        $this->assertGreaterThan(850, $counts['A']);
        $this->assertLessThan(1150, $counts['A']);
    }

    public function test_delivery_replaces_rotation_and_fails_closed_for_changed_or_unavailable_pair(): void
    {
        $experiment = $this->experiment();
        $experiment->start();
        $this->get('/')->assertOk();
        $first = AdvertisingExperiment::delivery($this->placement, []);
        $second = AdvertisingExperiment::delivery($this->placement, []);
        $this->assertSame($first['both']->id, $second['both']->id);
        $this->assertContains($first['both']->id, [$this->a->id, $this->b->id]);
        $rendered = (new BannerRenderer)->at('home-top');
        $this->assertSame($first['both']->id, $rendered['both']->id);
        $this->b->update(['title' => 'Changed design']);
        $this->assertSame([], AdvertisingExperiment::delivery($this->placement, []));
        $this->assertSame([], (new BannerRenderer)->at('home-top'));
        $experiment->stop();
        $this->assertNull(AdvertisingExperiment::delivery($this->placement, []));
    }

    public function test_experiment_events_are_signed_deduplicated_and_separate_from_ordinary_delivery(): void
    {
        $experiment = $this->experiment();
        $experiment->start();
        $this->get('/')->assertOk();
        $banner = AdvertisingExperiment::delivery($this->placement, [])['both'];
        $token = AdvertisingToken::issue($banner, 'home-top', 'both', []);
        $this->travel(2)->seconds();
        foreach (['impression', 'click', 'impression', 'click'] as $kind) {
            AdvertisingEvent::record($token, $kind, 'mobile');
        }
        $events = AdvertisingEvent::where('experiment_id', $experiment->id)->get();
        $this->assertCount(2, $events);
        $arm = $banner->experimentExposure['arm'];
        $this->assertSame([$arm], $events->pluck('experiment_arm')->unique()->all());
        $plain = AdvertisingToken::issue($banner->fresh(), 'home-top', 'both', []);
        AdvertisingEvent::record($plain, 'click', 'mobile');
        $this->assertSame(1, $experiment->report()[$arm]['clicks']);
        $this->assertSame(100.0, $experiment->report()[$arm]['ctr']);
        $other = $arm === 'A' ? 'B' : 'A';
        $this->assertNull($experiment->report()[$other]['ctr']);
        $this->assertSame(1, AdvertisingEvent::where('banner_id', $banner->id)->whereNull('experiment_id')->count());
    }

    public function test_stale_variant_and_stopped_tokens_cannot_charge(): void
    {
        $experiment = $this->experiment();
        $experiment->start();
        $this->get('/')->assertOk();
        $banner = AdvertisingExperiment::delivery($this->placement, [])['both'];
        $token = AdvertisingToken::issue($banner, 'home-top', 'both', []);
        $banner->update(['title' => 'Edited']);
        $this->travel(2)->seconds();
        AdvertisingEvent::record($token, 'impression', 'mobile');
        $this->assertSame(0, AdvertisingEvent::where('experiment_id', $experiment->id)->count());
        $experiment->stop();
        AdvertisingEvent::record($token, 'click', 'mobile');
        $this->assertSame(0, AdvertisingEvent::where('experiment_id', $experiment->id)->count());
    }

    public function test_campaign_counters_do_not_invalidate_experiment_but_targeting_changes_do(): void
    {
        $campaign = Campaign::create(['title' => 'Shared campaign', 'is_active' => true, 'impression_cost' => 10]);
        $this->a->update(['campaign_id' => $campaign->id]);
        $this->b->update(['campaign_id' => $campaign->id]);
        $experiment = $this->experiment();
        $experiment->start();
        $this->get('/')->assertOk();
        $banner = AdvertisingExperiment::delivery($this->placement, [])['both'];
        $token = AdvertisingToken::issue($banner, 'home-top', 'both', []);
        $this->travel(2)->seconds();
        AdvertisingEvent::record($token, 'impression', 'mobile');
        $this->assertSame(1, $campaign->fresh()->recorded_impressions);
        $this->assertNotEmpty(AdvertisingExperiment::delivery($this->placement, []));
        $campaign->update(['category_ids' => [1]]);
        $this->assertSame([], AdvertisingExperiment::delivery($this->placement, ['category_id' => 1]));
    }

    public function test_conversations_preserve_original_experiment_attribution_without_duplicates(): void
    {
        $experiment = $this->experiment();
        $experiment->start();
        $this->get('/')->assertOk();
        $click = AdvertisingEvent::create(['banner_id' => $this->a->id, 'experiment_id' => $experiment->id, 'experiment_arm' => 'A', 'placement' => 'home-top', 'device' => 'mobile', 'kind' => 'click', 'receipt' => hash('sha256', 'experiment-conversation'), 'target_listing_id' => 12345, 'event_date' => now()->toDateString()]);
        request()->cookies->set('advertising_attribution', json_encode(['id' => $click->id, 'at' => now()->timestamp]));
        AdvertisingEvent::recordConversation(12345);
        AdvertisingEvent::recordConversation(12345);
        $this->assertSame(1, $experiment->report()['A']['conversations']);
        $this->assertSame(1, AdvertisingEvent::where('source_click_id', $click->id)->count());
    }

    public function test_post_click_actions_preserve_experiment_arm_and_separate_counts_after_stop(): void
    {
        $experiment = $this->experiment();
        $experiment->start();
        $this->get('/')->assertOk();
        $click = AdvertisingEvent::create(['banner_id' => $this->a->id, 'experiment_id' => $experiment->id, 'experiment_arm' => 'A', 'placement' => 'home-top', 'device' => 'mobile', 'kind' => 'click', 'receipt' => hash('sha256', 'experiment-actions'), 'target_listing_id' => 12345, 'event_date' => now()->toDateString()]);
        request()->cookies->set('advertising_attribution', json_encode(['id' => $click->id, 'at' => now()->timestamp]));
        $experiment->stop();
        AdvertisingEvent::recordPostClick('service', 12345);
        AdvertisingEvent::recordPostClick('service', 12345);
        $shopClick = AdvertisingEvent::create(['banner_id' => $this->b->id, 'experiment_id' => $experiment->id, 'experiment_arm' => 'B', 'placement' => 'home-top', 'device' => 'desktop', 'kind' => 'click', 'receipt' => hash('sha256', 'experiment-shop-actions'), 'target_shop_id' => 23456, 'event_date' => now()->toDateString()]);
        request()->cookies->set('advertising_attribution', json_encode(['id' => $shopClick->id, 'at' => now()->timestamp]));
        AdvertisingEvent::recordPostClick('shop_view', 23456);
        AdvertisingEvent::recordPostClick('shop_view', 23456);
        $rows = $experiment->report();
        $this->assertSame(1, $rows['A']['service_requests']);
        $this->assertSame(0, $rows['A']['shop_views']);
        $this->assertSame(1, $rows['B']['shop_views']);
        $this->assertSame(0, $rows['B']['service_requests']);
        $this->assertSame(2, AdvertisingEvent::whereNotNull('source_click_id')->count());
        $html = view('advertising::experiments.report', ['experiment' => $experiment, 'rows' => $rows])->render();
        $this->assertStringContainsString(__('advertising::metrics.conversion_definition'), $html);
    }

    public function test_real_filament_creation_start_report_stop_and_admin_only_access(): void
    {
        Livewire::test(CreateExperiment::class)->fillForm(['name' => 'Filament comparison', 'placement_id' => $this->placement->id, 'banner_a_id' => $this->a->id, 'banner_b_id' => $this->b->id, 'duration_days' => 7])->call('create')->assertHasNoFormErrors();
        $experiment = AdvertisingExperiment::where('name', 'Filament comparison')->firstOrFail();
        Livewire::test(ListExperiments::class)->callTableAction('start', $experiment)->assertHasNoTableActionErrors();
        $this->assertSame('running', $experiment->fresh()->state);
        Livewire::test(ListExperiments::class)->assertTableActionExists('report');
        $this->assertStringContainsString(__('advertising::experiments.no_winner'), view('advertising::experiments.report', ['experiment' => $experiment->fresh(), 'rows' => $experiment->report()])->render());
        Livewire::test(ListExperiments::class)->callTableAction('stop', $experiment)->assertHasNoTableActionErrors();
        $this->assertSame('stopped', $experiment->fresh()->state);
        $staff = User::factory()->create(['status' => 'active']);
        $staff->syncRoles([]);
        $staff->givePermissionTo('advertising.manage');
        $this->actingAs($staff);
        $this->assertFalse(AdvertisingExperimentResource::canViewAny());
        $this->get(AdvertisingExperimentResource::getUrl())->assertForbidden();
        $this->expectException(HttpException::class);
        $experiment->report();
    }
}
