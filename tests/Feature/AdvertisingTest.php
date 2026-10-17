<?php

declare(strict_types=1);

namespace Tests\Feature;

use Filament\Facades\Filament;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Modules\Advertising\Filament\Admin\Resources\BannerPlacementResource\Pages\EditPlacement;
use Modules\Advertising\Filament\Admin\Resources\BannerResource;
use Modules\Advertising\Filament\Admin\Resources\BannerResource\Pages\CreateBanner;
use Modules\Advertising\Models\Banner;
use Modules\Advertising\Models\BannerAssignment;
use Modules\Advertising\Models\BannerPlacement;
use Modules\Advertising\Support\BannerRenderer;
use Modules\Listing\Models\Listing;
use Modules\Page\Models\Page;
use Modules\User\App\Models\User;
use Tests\TestCase;

final class AdvertisingTest extends TestCase
{
    private function banner(array $data = []): Banner
    {
        return Banner::create(array_replace(['title' => 'Test campaign', 'image' => 'banners/desktop.jpg', 'alt' => 'A promotional banner', 'device' => 'both', 'is_active' => true], $data));
    }

    private function assign(Banner $banner, string $key = 'home-feed', int $priority = 1): BannerPlacement
    {
        $placement = BannerPlacement::where('key', $key)->firstOrFail();
        $placement->assignments()->create(['banner_id' => $banner->id, 'priority' => $priority]);

        return $placement;
    }

    private function admin(): void
    {
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $this->actingAs(User::where('email', 'a@a.com')->firstOrFail());
    }

    public function test_priority_frequency_and_stop_apply_across_page_boundaries(): void
    {
        $second = $this->banner(['title' => 'Second']);
        $first = $this->banner(['title' => 'First']);
        $placement = $this->assign($second, priority: 20);
        $this->assign($first, priority: 1);
        $placement->update(['first_after' => 3, 'interval' => 4]);
        $renderer = new BannerRenderer;
        $this->assertSame([], $renderer->at('home-feed', 2));
        $this->assertSame($first->id, $renderer->at('home-feed', 3)['both']->id);
        $this->assertSame([], $renderer->at('home-feed', 6));
        $this->assertSame($second->id, $renderer->at('home-feed', 7)['both']->id);
        $this->assertSame([], $renderer->at('home-feed', 11));
        $placement->update(['repeat' => true]);
        $this->assertSame($first->id, (new BannerRenderer)->at('home-feed', 11)['both']->id);
    }

    public function test_expired_future_disabled_and_deleted_banners_are_excluded(): void
    {
        $this->travelTo(now()->startOfSecond());
        foreach ([['ends_at' => now()], ['starts_at' => now()->addMinute()], ['is_active' => false]] as $data) {
            $this->assign($this->banner($data));
        }
        $deleted = $this->banner();
        $this->assign($deleted);
        $deleted->delete();
        $valid = $this->banner(['starts_at' => now(), 'ends_at' => now()->addMinute()]);
        $placement = $this->assign($valid);
        $this->assertSame($valid->id, (new BannerRenderer)->at('home-feed', 4)['both']->id);
        $placement->update(['is_active' => false]);
        $this->assertSame([], (new BannerRenderer)->at('home-feed', 4));
    }

    public function test_priority_is_independent_per_placement_and_devices_have_fallback(): void
    {
        $mobile = $this->banner(['device' => 'mobile', 'mobile_image' => 'banners/mobile.jpg']);
        $desktop = $this->banner(['device' => 'desktop']);
        $this->assign($desktop, priority: 1);
        $this->assign($mobile, priority: 2);
        $items = (new BannerRenderer)->at('home-feed', 4);
        $this->assertSame($mobile->id, $items['mobile']->id);
        $this->assertSame($desktop->id, $items['desktop']->id);
        $this->assertStringEndsWith('/banners/mobile.jpg', $mobile->imageUrl(true));
        $this->assertSame($desktop->imageUrl(), $desktop->imageUrl(true));
        $this->assign($desktop, 'page-top', 9);
        $this->assign($mobile, 'page-top', 1);
        $this->assertSame($mobile->id, (new BannerRenderer)->at('page-top')['mobile']->id);
    }

    public function test_no_repeats_and_stable_random_order_within_a_session(): void
    {
        for ($i = 0; $i < 4; $i++) {
            $placement = $this->assign($this->banner());
        }
        $placement->update(['ordering' => 'random']);
        $first = new BannerRenderer;
        $second = new BannerRenderer;
        $ids = [];
        foreach ([4, 10, 16, 22] as $position) {
            $ids[] = $id = $first->at('home-feed', $position)['both']->id;
            $this->assertSame($id, $second->at('home-feed', $position)['both']->id);
        }
        $this->assertCount(4, array_unique($ids));
        $this->assertSame([], $first->at('home-feed', 28));
    }

    public function test_deleted_assignments_disappear_and_can_be_reassigned(): void
    {
        $banner = $this->banner();
        $this->assign($banner);
        BannerAssignment::where('banner_id', $banner->id)->firstOrFail()->delete();
        $this->assertSame([], (new BannerRenderer)->at('home-feed', 4));
        $this->assign($banner);
        $this->assertSame($banner->id, (new BannerRenderer)->at('home-feed', 4)['both']->id);
    }

    public function test_renderer_queries_a_placement_once_per_request_and_rejects_unknown_keys(): void
    {
        $this->assign($this->banner());
        $renderer = new BannerRenderer;
        $connection = Banner::resolveConnection();
        $connection->enableQueryLog();
        $renderer->at('home-feed', 4);
        $count = count($connection->getQueryLog());
        $renderer->at('home-feed', 10);
        $renderer->at('home-feed', 16);
        $this->assertSame($count, count($connection->getQueryLog()));
        $connection->disableQueryLog();
        $this->assertSame([], $renderer->at('unknown'));
    }

    public function test_public_slots_render_on_home_content_listing_and_results(): void
    {
        $banner = $this->banner(['url' => 'https://example.com/sponsor', 'new_tab' => true, 'mobile_image' => 'banners/mobile.jpg']);
        foreach (['home-top', 'home-feed', 'listings-feed', 'search-feed', 'category-feed', 'listing-details', 'listing-related', 'page-top', 'page-bottom'] as $key) {
            $p = $this->assign($banner, $key);
            $p->update(['first_after' => 1]);
        }
        $this->get('/')->assertSuccessful()->assertSee('data-placement="home-feed"', false)->assertSee('banners/mobile.jpg', false)->assertSee('rel="sponsored noopener noreferrer"', false);
        $this->get('/listings')->assertSuccessful()->assertSee('data-placement="listings-feed"', false);
        $listing = Listing::query()->where('status', 'active')->firstOrFail();
        $this->get('/listings?search='.urlencode($listing->title))->assertSuccessful()->assertSee('data-placement="search-feed"', false);
        $this->get('/listings?category='.$listing->category_id)->assertSuccessful()->assertSee('data-placement="category-feed"', false);
        $this->get(route('listings.show', $listing))->assertSuccessful()->assertSee('data-placement="listing-details"', false)->assertSee('data-placement="listing-related"', false);
        $page = Page::where('is_published', true)->firstOrFail();
        $this->get(route('pages.show', $page->slug))->assertSuccessful()->assertSee('data-placement="page-top"', false)->assertSee('data-placement="page-bottom"', false);
    }

    public function test_paginated_results_keep_the_global_insertion_offset(): void
    {
        $banner = $this->banner();
        $placement = $this->assign($banner, 'listings-feed');
        $placement->update(['first_after' => 17, 'interval' => 16, 'repeat' => true]);
        $this->get('/listings')->assertSuccessful()->assertDontSee('data-placement="listings-feed"', false);
        $this->get('/listings?page=2')->assertSuccessful()->assertSee('data-placement="listings-feed"', false);
    }

    public function test_untrusted_link_schemes_are_not_clickable_and_alt_is_escaped(): void
    {
        $banner = $this->banner(['url' => 'javascript:alert(1)', 'alt' => '"><script>alert(1)</script>']);
        $this->assign($banner, 'home-top');
        $html = view('advertising::slot', ['placementKey' => 'home-top'])->render();
        $this->assertStringNotContainsString('href=', $html);
        $this->assertStringNotContainsString('<script>', $html);
        $this->assertStringContainsString('&lt;script&gt;', $html);
    }

    public function test_admin_can_create_a_banner_with_uploaded_images_and_multiple_priorities(): void
    {
        $this->admin();
        Storage::fake('public');
        $p = BannerPlacement::where('key', 'home-feed')->firstOrFail();
        Livewire::test(CreateBanner::class)->fillForm([
            'title' => 'Admin campaign', 'alt' => 'Campaign image', 'image' => UploadedFile::fake()->image('desktop.jpg', 1200, 400),
            'mobile_image' => UploadedFile::fake()->image('mobile.jpg', 800, 400), 'url' => 'https://example.com',
            'device' => 'both', 'is_active' => true, 'new_tab' => true,
            'assignments' => [['placement_id' => $p->id, 'priority' => 7]],
        ])->call('create')->assertHasNoFormErrors();
        $banner = Banner::where('title', 'Admin campaign')->firstOrFail();
        Storage::disk('public')->assertExists($banner->image);
        Storage::disk('public')->assertExists($banner->mobile_image);
        $this->assertSame(7, $banner->assignments()->firstOrFail()->priority);
    }

    public function test_end_date_without_a_start_date_and_placement_device_restriction(): void
    {
        $this->admin();
        Storage::fake('public');
        Livewire::test(CreateBanner::class)->fillForm([
            'title' => 'End date only', 'alt' => 'Image', 'image' => UploadedFile::fake()->image('banner.jpg'),
            'device' => 'both', 'is_active' => true, 'starts_at' => null,
            'ends_at' => now()->addDay()->format('Y-m-d H:i:s'),
        ])->call('create')->assertHasNoFormErrors();
        $banner = Banner::where('title', 'End date only')->firstOrFail();
        $p = $this->assign($banner);
        $p->update(['device' => 'mobile']);
        $result = (new BannerRenderer)->at('home-feed', 4);
        $this->assertArrayHasKey('mobile', $result);
        $this->assertArrayNotHasKey('desktop', $result);
    }

    public function test_admin_validation_rejects_bad_url_schedule_and_zero_interval(): void
    {
        $this->admin();
        Livewire::test(CreateBanner::class)->fillForm(['title' => 'Invalid', 'alt' => 'Image', 'url' => 'javascript:alert(1)', 'starts_at' => now()->addDay()->format('Y-m-d H:i:s'), 'ends_at' => now()->format('Y-m-d H:i:s')])->call('create')->assertHasFormErrors(['url', 'ends_at', 'image']);
        $p = BannerPlacement::where('key', 'home-feed')->firstOrFail();
        Livewire::test(EditPlacement::class, ['record' => $p->id])->fillForm(['interval' => 0, 'first_after' => 0])->call('save')->assertHasFormErrors(['interval', 'first_after']);
    }

    public function test_admin_can_access_management_but_members_cannot(): void
    {
        $this->admin();
        $this->get('/admin/banners')->assertSuccessful();
        $this->get('/admin/banners/create')->assertSuccessful();
        $this->get('/admin/banner-placements')->assertSuccessful();
        $this->actingAs(User::where('email', 'b@b.com')->firstOrFail());
        $this->assertFalse(BannerResource::canViewAny());
        $this->get('/admin/banners')->assertForbidden();
        $this->get('/admin/banners/create')->assertForbidden();
        $p = BannerPlacement::where('key', 'home-feed')->firstOrFail();
        $this->get('/admin/banner-placements/'.$p->id.'/edit')->assertForbidden();
    }
}
