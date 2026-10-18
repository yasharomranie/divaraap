<?php

declare(strict_types=1);

namespace Tests\Feature;

use Filament\Facades\Filament;
use Livewire\Livewire;
use Modules\Advertising\Filament\Admin\Resources\BannerPlacementResource\Pages\EditPlacement;
use Modules\Advertising\Filament\Admin\Resources\CampaignResource\Pages\CreateCampaign;
use Modules\Advertising\Models\Banner;
use Modules\Advertising\Models\BannerAssignment;
use Modules\Advertising\Models\BannerPlacement;
use Modules\Advertising\Models\Campaign;
use Modules\Advertising\Support\BannerRenderer;
use Modules\Advertising\Support\CampaignAllocation;
use Modules\User\App\Models\User;
use Tests\TestCase;

final class CampaignAllocationTest extends TestCase
{
    private function campaign(int $weight = 1): Campaign
    {
        return Campaign::create(['title' => 'Weighted campaign', 'is_active' => true, 'delivery_weight' => $weight]);
    }

    private function assignment(?Campaign $campaign, int $priority = 1, string $device = 'both'): BannerAssignment
    {
        $banner = Banner::create(['title' => 'Creative', 'image' => 'banners/allocation.jpg', 'alt' => 'Banner', 'is_active' => true, 'campaign_id' => $campaign?->id, 'device' => $device]);
        $assignment = BannerAssignment::create(['placement_id' => BannerPlacement::where('key', 'home-feed')->firstOrFail()->id, 'banner_id' => $banner->id, 'priority' => $priority]);
        $assignment->load('banner.campaign');

        return $assignment;
    }

    public function test_first_slot_weight_is_per_campaign_not_number_of_creatives(): void
    {
        $large = $this->campaign();
        $small = $this->campaign();
        $items = collect();
        for ($i = 0; $i < 8; $i++) {
            $items->push($this->assignment($large));
        }
        $items->push($this->assignment($small));
        $allocator = new CampaignAllocation;
        $wins = 0;
        for ($i = 0; $i < 2000; $i++) {
            $wins += (int) ($allocator->select($items, 0, false, 'visitor-'.$i)->banner->campaign_id === $large->id);
        }
        $this->assertGreaterThan(900, $wins);
        $this->assertLessThan(1100, $wins);
        $large->update(['delivery_weight' => 3]);
        $items->each(fn ($item) => $item->banner->unsetRelation('campaign')->load('campaign'));
        $wins = 0;
        for ($i = 0; $i < 2000; $i++) {
            $wins += (int) ($allocator->select($items, 0, false, 'visitor-'.$i)->banner->campaign_id === $large->id);
        }
        $this->assertGreaterThan(1400, $wins);
        $this->assertLessThan(1600, $wins);
    }

    public function test_priority_inside_campaign_no_repeat_and_pagination_are_stable(): void
    {
        $campaign = $this->campaign(3);
        $low = $this->assignment($campaign, 20);
        $high = $this->assignment($campaign, 1);
        $other = $this->assignment($this->campaign());
        $items = collect([$low, $other, $high]);
        $allocator = new CampaignAllocation;
        $sequence = [];
        for ($slot = 0; $slot < 3; $slot++) {
            $selected = $allocator->select($items, $slot, false, 'stable');
            $sequence[] = $selected->id;
            $this->assertSame($selected->id, (new CampaignAllocation)->select($items, $slot, false, 'stable')->id);
        }
        $this->assertCount(3, array_unique($sequence));
        $this->assertLessThan(array_search($low->id, $sequence), array_search($high->id, $sequence));
        $this->assertNull($allocator->select($items, 3, false, 'stable'));
        $this->assertNull($allocator->select(collect(), 0, true, 'stable'));
    }

    public function test_repeat_rotates_campaign_creatives_in_priority_order(): void
    {
        $campaign = $this->campaign(3);
        $first = $this->assignment($campaign, 1);
        $second = $this->assignment($campaign, 2);
        $other = $this->assignment($this->campaign());
        $allocator = new CampaignAllocation;
        $sequence = [];
        for ($slot = 0; $slot < 30; $slot++) {
            $item = $allocator->select(collect([$second, $other, $first]), $slot, true, 'repeat');
            if ($item->banner->campaign_id === $campaign->id) {
                $sequence[] = $item->id;
            }
        }
        $this->assertGreaterThan(10, count($sequence));
        foreach ($sequence as $index => $id) {
            $this->assertSame($index % 2 === 0 ? $first->id : $second->id, $id);
        }
    }

    public function test_renderer_respects_eligibility_and_device_fallback_in_weighted_mode(): void
    {
        $paused = $this->campaign(1000);
        $paused->update(['is_active' => false]);
        $this->assignment($paused);
        $mobile = $this->assignment($this->campaign(1), device: 'mobile');
        $desktop = $this->assignment(null, device: 'desktop');
        $placement = BannerPlacement::where('key', 'home-feed')->firstOrFail();
        $placement->update(['ordering' => 'campaign']);
        $renderer = new BannerRenderer;
        $result = $renderer->at('home-feed', 4);
        $this->assertSame($mobile->banner_id, $result['mobile']->id);
        $this->assertSame($desktop->banner_id, $result['desktop']->id);
        $this->assertSame([], $renderer->at('home-feed', 10));
    }

    public function test_independent_banners_receive_unit_weight(): void
    {
        $campaign = $this->assignment($this->campaign(3));
        $independent = $this->assignment(null);
        $wins = 0;
        $allocator = new CampaignAllocation;
        for ($i = 0; $i < 2000; $i++) {
            $wins += (int) ($allocator->select(collect([$campaign, $independent]), 0, false, 'independent-'.$i)->id === $independent->id);
        }
        $this->assertGreaterThan(400, $wins);
        $this->assertLessThan(600, $wins);
    }

    public function test_admin_can_configure_weights_and_opt_in_per_placement(): void
    {
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $this->actingAs(User::where('email', 'a@a.com')->firstOrFail());
        Livewire::test(CreateCampaign::class)->fillForm(['title' => 'Weighted admin campaign', 'goal' => 'awareness', 'delivery_weight' => 3])->call('create')->assertHasNoFormErrors();
        $this->assertSame(3, Campaign::where('title', 'Weighted admin campaign')->firstOrFail()->delivery_weight);
        foreach ([0, -1, 1001] as $weight) {
            Livewire::test(CreateCampaign::class)->fillForm(['title' => 'Invalid weight', 'goal' => 'awareness', 'delivery_weight' => $weight])->call('create')->assertHasFormErrors(['delivery_weight']);
        }
        $placement = BannerPlacement::where('key', 'home-feed')->firstOrFail();
        Livewire::test(EditPlacement::class, ['record' => $placement->id])->fillForm(['ordering' => 'campaign'])->call('save')->assertHasNoFormErrors();
        $this->assertSame('campaign', $placement->fresh()->ordering);
    }
}
