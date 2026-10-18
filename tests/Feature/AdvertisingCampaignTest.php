<?php

declare(strict_types=1);

namespace Tests\Feature;

use Filament\Facades\Filament;
use Livewire\Livewire;
use Modules\Advertising\Filament\Admin\Resources\CampaignResource;
use Modules\Advertising\Filament\Admin\Resources\CampaignResource\Pages\CreateCampaign;
use Modules\Advertising\Models\Banner;
use Modules\Advertising\Models\BannerPlacement;
use Modules\Advertising\Models\Campaign;
use Modules\Advertising\Support\BannerRenderer;
use Modules\Category\Models\Category;
use Modules\Listing\Models\Listing;
use Modules\Location\Models\City;
use Modules\User\App\Models\User;
use Tests\TestCase;

final class AdvertisingCampaignTest extends TestCase
{
    private function campaign(array $data = []): Campaign
    {
        return Campaign::create(array_replace(['title' => 'Seasonal campaign', 'goal' => 'traffic', 'is_active' => true], $data));
    }

    private function assign(?Campaign $campaign, string $key = 'home-top'): Banner
    {
        $banner = Banner::create(['title' => 'Campaign banner', 'image' => 'banners/campaign.jpg', 'alt' => 'Campaign image', 'is_active' => true, 'device' => 'both', 'campaign_id' => $campaign?->id]);
        BannerPlacement::where('key', $key)->firstOrFail()->assignments()->create(['banner_id' => $banner->id, 'priority' => 1]);

        return $banner;
    }

    public function test_campaign_schedule_and_pause_apply_without_changing_banner_status(): void
    {
        $this->travelTo(now()->startOfSecond());
        foreach ([['is_active' => false], ['starts_at' => now()->addMinute()], ['ends_at' => now()]] as $data) {
            $banner = $this->assign($this->campaign($data));
            $this->assertSame([], (new BannerRenderer)->at('home-top'));
            $this->assertTrue($banner->fresh()->is_active);
            $banner->delete();
        }
        $campaign = $this->campaign(['starts_at' => now(), 'ends_at' => now()->addMinute()]);
        $banner = $this->assign($campaign);
        $this->assertSame($banner->id, (new BannerRenderer)->at('home-top')['both']->id);
        $campaign->delete();
        $this->assertSame([], (new BannerRenderer)->at('home-top'));
        $banner->update(['campaign_id' => null]);
        $this->assertSame($banner->id, (new BannerRenderer)->at('home-top')['both']->id);
    }

    public function test_all_targeting_conditions_must_match_and_missing_context_is_excluded(): void
    {
        $campaign = $this->campaign(['category_ids' => [12], 'city_ids' => ['34'], 'page_paths' => ['/listings']]);
        $banner = $this->assign($campaign);
        $renderer = new BannerRenderer;
        $valid = ['category_id' => 12, 'city_id' => 34, 'path' => '/listings'];
        $this->assertSame($banner->id, $renderer->at('home-top', context: $valid)['both']->id);
        foreach ([[], array_replace($valid, ['city_id' => 35]), array_replace($valid, ['category_id' => 13]), array_replace($valid, ['path' => '/listings/10'])] as $context) {
            $this->assertSame([], $renderer->at('home-top', context: $context));
        }
    }

    public function test_targeted_campaign_renders_only_for_matching_real_listing_context(): void
    {
        $listing = Listing::where('status', 'active')->firstOrFail();
        $city = City::with('country')->firstOrFail();
        $listing->update(['city' => $city->name, 'country' => $city->country->name]);
        $campaign = $this->campaign(['category_ids' => [$listing->category_id], 'city_ids' => [$city->id]]);
        $this->assign($campaign, 'listing-details');
        $this->assign($campaign, 'listings-feed');
        $this->assign($campaign, 'category-feed');
        BannerPlacement::whereIn('key', ['listings-feed', 'category-feed'])->update(['first_after' => 1]);
        $this->get(route('listings.show', $listing))->assertSuccessful()->assertSee('data-placement="listing-details"', false);
        $this->get('/listings')->assertSuccessful()->assertDontSee('data-placement="listings-feed"', false);
        $this->get('/listings?category='.$listing->category_id.'&city='.$city->id)->assertSuccessful()->assertSee('data-placement="category-feed"', false);
        $campaign->update(['page_paths' => ['/not-this-page']]);
        app()->forgetInstance(BannerRenderer::class);
        $this->get(route('listings.show', $listing))->assertSuccessful()->assertDontSee('data-placement="listing-details"', false);
    }

    public function test_campaign_cannot_bypass_banner_approval_or_schedule(): void
    {
        $campaign = $this->campaign();
        foreach ([['review_status' => 'pending'], ['is_active' => false], ['ends_at' => now()->subMinute()]] as $data) {
            $banner = $this->assign($campaign);
            $banner->update($data);
            $this->assertSame([], (new BannerRenderer)->at('home-top'));
            $banner->delete();
        }
    }

    public function test_admin_can_create_campaign_with_existing_category_and_city(): void
    {
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $this->actingAs(User::where('email', 'a@a.com')->firstOrFail());
        $category = Category::activeIdNameOptions();
        $city = City::advertisingOptions();
        Livewire::test(CreateCampaign::class)->fillForm([
            'title' => 'Summer campaign', 'goal' => 'traffic', 'is_active' => true, 'planned_budget' => 1000000,
            'category_ids' => [array_key_first($category)], 'city_ids' => [array_key_first($city)],
            'page_paths' => [['path' => '/listings']], 'ends_at' => now()->addDay()->format('Y-m-d H:i:s'),
        ])->call('create')->assertHasNoFormErrors();
        $campaign = Campaign::where('title', 'Summer campaign')->firstOrFail();
        $this->assertSame(['/listings'], $campaign->page_paths);
        $this->assertSame(1000000, $campaign->planned_budget);
        $this->get(CampaignResource::getUrl())->assertSuccessful();
    }

    public function test_form_rejects_external_paths_bad_goal_budget_and_dates(): void
    {
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $this->actingAs(User::where('email', 'a@a.com')->firstOrFail());
        Livewire::test(CreateCampaign::class)->fillForm([
            'title' => 'Invalid campaign', 'goal' => 'invalid', 'planned_budget' => -1,
            'page_paths' => [['path' => 'https://example.com']], 'starts_at' => now()->addDay()->format('Y-m-d H:i:s'), 'ends_at' => now()->format('Y-m-d H:i:s'),
        ])->call('create')->assertHasFormErrors(['goal', 'planned_budget', 'ends_at']);
        $this->assertFalse(Campaign::where('title', 'Invalid campaign')->exists());
    }

    public function test_only_advertising_staff_can_manage_campaigns(): void
    {
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $user = User::factory()->create(['status' => 'active']);
        $this->actingAs($user);
        $this->assertFalse(CampaignResource::canViewAny());
        $this->get(CampaignResource::getUrl())->assertForbidden();
        $user->assignRole('advertising-manager');
        $this->assertTrue(CampaignResource::canViewAny());
        $this->get(CampaignResource::getUrl())->assertSuccessful();
        Livewire::test(CreateCampaign::class)->fillForm(['title' => 'Staff campaign', 'goal' => 'awareness'])->call('create')->assertHasNoFormErrors();
        $user->update(['status' => 'suspended']);
        $this->assertFalse(CampaignResource::canViewAny());
    }
}
