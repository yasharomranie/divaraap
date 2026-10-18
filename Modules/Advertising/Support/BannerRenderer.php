<?php

declare(strict_types=1);

namespace Modules\Advertising\Support;

use Modules\Admin\Support\AdvertisingCapacity;
use Modules\Advertising\Models\BannerPlacement;
use Modules\Location\Models\City;

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
        $assignments = $placement->assignments->filter(fn ($a) => $a->banner && filled($a->banner->image) && $a->banner->eligibleForCampaign($context));
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
            $output[$device] = $items[$slot % $items->count()]->banner;
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
