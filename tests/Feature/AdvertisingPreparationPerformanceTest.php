<?php

declare(strict_types=1);

namespace Tests\Feature;

use Modules\Advertising\Models\Banner;
use Modules\Advertising\Models\BannerPlacement;
use Modules\Advertising\Models\Campaign;
use Modules\Advertising\Support\BannerRenderer;
use Modules\Category\Models\Category;
use Modules\Listing\Models\Listing;
use Modules\User\App\Models\Profile;
use Modules\User\App\Models\User;
use Tests\TestCase;

final class AdvertisingPreparationPerformanceTest extends TestCase
{
    public function test_feed_preparation_queries_do_not_grow_with_slot_count(): void
    {
        $seller = User::factory()->create(['status' => 'active']);
        Profile::saveShopFor($seller, ['shop_enabled' => true, 'shop_name' => 'Performance shop'], Profile::shopVersion($seller));
        $listing = Listing::unguarded(fn () => Listing::create(['title' => 'Performance service', 'description' => 'Service', 'slug' => 'performance-'.bin2hex(random_bytes(10)), 'user_id' => $seller->id, 'category_id' => Category::where('slug', 'services')->firstOrFail()->id, 'status' => 'active', 'price' => 1000, 'currency' => 'IRR']));
        $second = $listing->replicate();
        $second->slug .= '-second';
        $second->save();
        $placement = BannerPlacement::where('key', 'home-feed')->firstOrFail();
        $placement->update(['first_after' => 1, 'interval' => 1, 'repeat' => true]);
        foreach ([['creative_format' => 'service', 'service_listing_id' => $listing->id], ['creative_format' => 'shop', 'shop_user_id' => $seller->id], ['creative_format' => 'collection', 'collection_listing_ids' => [$listing->id, $second->id]]] as $priority => $data) {
            $banner = Banner::create($data + ['title' => 'Performance banner', 'image' => 'banners/performance.jpg', 'alt' => 'Performance', 'device' => 'both', 'is_active' => true, 'review_status' => 'approved']);
            $placement->assignments()->create(['banner_id' => $banner->id, 'priority' => $priority]);
        }
        $renderer = new BannerRenderer;
        $connection = Banner::resolveConnection();
        $connection->enableQueryLog();
        try {
            $first = $renderer->at('home-feed', 1);
            $count = count($connection->getQueryLog());
            $this->assertNotEmpty($first);
            foreach (range(2, 6) as $position) {
                $this->assertNotEmpty($renderer->at('home-feed', $position));
            }
            $this->assertSame($count, count($connection->getQueryLog()), 'Preparing five more slots must not repeat public creative queries.');
        } finally {
            $connection->disableQueryLog();
            $connection->flushQueryLog();
        }
        Profile::detailsForUser($seller)->forceFill(['shop_enabled' => false])->save();
        $listing->forceFill(['status' => 'paused'])->save();
        $second->forceFill(['status' => 'paused'])->save();
        $this->assertSame([], (new BannerRenderer)->at('home-feed', 1));
    }

    public function test_preparation_reuse_does_not_cache_contextual_campaign_eligibility(): void
    {
        $campaign = Campaign::create(['title' => 'Contextual performance campaign', 'is_active' => true, 'page_paths' => ['/listings']]);
        $banner = Banner::create(['title' => 'Contextual banner', 'image' => 'banners/context.jpg', 'alt' => 'Context', 'device' => 'both', 'is_active' => true, 'review_status' => 'approved', 'campaign_id' => $campaign->id]);
        $placement = BannerPlacement::where('key', 'listings-feed')->firstOrFail();
        $placement->update(['first_after' => 1, 'interval' => 1, 'repeat' => true]);
        $placement->assignments()->create(['banner_id' => $banner->id, 'priority' => 1]);
        $renderer = new BannerRenderer;
        $this->assertSame([], $renderer->at('listings-feed', 1, ['path' => 'other']));
        $this->assertSame($banner->id, $renderer->at('listings-feed', 2, ['path' => 'listings'])['both']->id);
        $this->assertSame([], $renderer->at('listings-feed', 3, ['path' => 'other']));
    }
}
