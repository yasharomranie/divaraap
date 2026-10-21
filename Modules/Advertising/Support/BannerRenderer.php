<?php

declare(strict_types=1);

namespace Modules\Advertising\Support;

use Modules\Admin\Support\AdvertisingCapacity;
use Modules\Advertising\Models\BannerPlacement;
use Modules\Category\Models\Category;
use Modules\Listing\Models\Listing;
use Modules\Location\Models\City;
use Modules\Page\Models\Page;
use Modules\User\App\Models\Profile;
use Modules\Video\Models\Video;

final class BannerRenderer
{
    private array $placements = [];

    private array $cities = [];

    public function at(string $key, int $position = 0, array $context = []): array
    {
        if (! isset($context['city_id']) && isset($context['city_name'], $context['country_name'])) {
            $locationKey = json_encode([$context['city_name'], $context['country_name']]);
            if (! array_key_exists($locationKey, $this->cities)) {
                $this->cities[$locationKey] = City::advertisingIdForLocation($context['city_name'], $context['country_name']);
            }
            $context['city_id'] = $this->cities[$locationKey];
        }
        if (! array_key_exists($key, $this->placements)) {
            $this->placements[$key] = BannerPlacement::available($key);
        }
        $placement = $this->placements[$key];
        $slot = $placement?->slotIndex($position);
        if ($slot === null) {
            return [];
        }
        $collectionIds = $placement->assignments->flatMap(fn ($assignment): array => $assignment->banner?->creative_format === 'collection' ? ($assignment->banner->collection_listing_ids ?? []) : [])->unique()->all();
        $collectionItems = Listing::advertisingCollectionItems($collectionIds);
        foreach ($placement->assignments as $assignment) {
            if ($assignment->banner?->creative_format === 'collection') {
                $assignment->banner->cacheAdvertisedListings($collectionItems);
            }
        }
        $filterBanners = $placement->assignments->filter(fn ($assignment): bool => $assignment->banner?->creative_format === 'filter');
        if ($filterBanners->isNotEmpty()) {
            $filterCategories = Category::advertisingFilterOptions();
            $filterCities = City::advertisingFilterOptions();
            foreach ($filterBanners as $assignment) {
                $assignment->banner->preparedFilters($filterCategories, $filterCities);
            }
        }
        $guides = Page::advertisingGuides($placement->assignments->map(fn ($assignment) => $assignment->banner?->creative_format === 'guide' ? $assignment->banner->guide_page_id : null)->filter()->unique()->all());
        foreach ($placement->assignments as $assignment) {
            if ($assignment->banner?->creative_format === 'guide') {
                $assignment->banner->cacheAdvertisedGuide($guides[$assignment->banner->guide_page_id] ?? null);
            }
        }
        $clips = Video::advertisingClips($placement->assignments->map(fn ($assignment) => $assignment->banner?->creative_format === 'video' ? $assignment->banner->video_id : null)->filter()->unique()->all());
        foreach ($placement->assignments as $assignment) {
            if ($assignment->banner?->creative_format === 'video') {
                $assignment->banner->cacheAdvertisedVideo($clips[$assignment->banner->video_id] ?? null);
            }
        }
        $deadlineListings = Listing::advertisingDeadlineItems($placement->assignments->map(fn ($assignment) => $assignment->banner?->creative_format === 'deadline' ? $assignment->banner->offer_listing_id : null)->filter()->unique()->all());
        foreach ($placement->assignments as $assignment) {
            if ($assignment->banner?->creative_format === 'deadline') {
                $assignment->banner->cacheDeadlineListing($deadlineListings[$assignment->banner->offer_listing_id] ?? null);
            }
        }
        $services = Listing::advertisingServiceItems($placement->assignments->map(fn ($assignment) => $assignment->banner?->creative_format === 'service' ? $assignment->banner->service_listing_id : null)->filter()->unique()->all());
        foreach ($placement->assignments as $assignment) {
            if ($assignment->banner?->creative_format === 'service') {
                $assignment->banner->cacheAdvertisedService($services[$assignment->banner->service_listing_id] ?? null);
            }
        }
        $shops = Profile::advertisingShops($placement->assignments->map(fn ($assignment) => in_array($assignment->banner?->creative_format, ['shop', 'sponsor'], true) ? $assignment->banner->shop_user_id : null)->filter()->unique()->all());
        foreach ($placement->assignments as $assignment) {
            if (in_array($assignment->banner?->creative_format, ['shop', 'sponsor'], true)) {
                $assignment->banner->cacheAdvertisedShop($shops[$assignment->banner->shop_user_id] ?? null);
            }
        }
        $sponsors = $placement->assignments->filter(fn ($assignment): bool => $assignment->banner?->creative_format === 'sponsor');
        if ($sponsors->isNotEmpty()) {
            $categories = Category::advertisingFilterOptions();
            foreach ($sponsors as $assignment) {
                $assignment->banner->cacheSponsoredCategory($categories[$assignment->banner->sponsored_category_id] ?? null);
            }
        }
        $assignments = $placement->assignments->filter(fn ($a) => $a->banner && $a->banner->eligibleForPlacement($key) && filled($a->banner->image) && $a->banner->eligibleForCampaign($context) && app(AdvertisingFrequency::class)->allows($a->banner));
        if ($placement->ordering === 'random') {
            $seed = request()->hasSession() ? request()->session()->getId() : now()->toDateString();
            $assignments = $assignments->sortBy(fn ($a) => hash('sha256', $seed.'|'.$key.'|'.$a->id));
        }
        $output = [];
        foreach (['mobile', 'desktop'] as $device) {
            if (! in_array($placement->device, ['both', $device], true)) {
                continue;
            }
            $items = $assignments->filter(fn ($a) => in_array($a->banner->device, ['both', $device], true))->values();
            if ($items->isEmpty() || (! $placement->repeat && $slot >= $items->count())) {
                continue;
            }
            if ($placement->ordering === 'campaign') {
                $session = request()->hasSession() ? request()->session()->getId() : 'guest';
                $assignment = app(CampaignAllocation::class)->select($items, $slot, $placement->repeat, $session.'|'.now()->toDateString().'|'.$key);
                if ($assignment) {
                    $output[$device] = $assignment->banner;
                }
            } else {
                $output[$device] = $items[$slot % $items->count()]->banner;
            }
        }
        if ($output !== [] && ! app(AdvertisingCapacity::class)->reserve($key, $position)) {
            return [];
        }
        if (isset($output['mobile'], $output['desktop']) && $output['mobile']->id === $output['desktop']->id) {
            return ['both' => $output['mobile']];
        }

        return $output;
    }
}
