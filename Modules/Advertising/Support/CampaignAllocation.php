<?php

declare(strict_types=1);

namespace Modules\Advertising\Support;

use Illuminate\Support\Collection;
use Modules\Advertising\Models\BannerAssignment;

final class CampaignAllocation
{
    public function select(Collection $assignments, int $slot, bool $repeat, string $seed): ?BannerAssignment
    {
        if ($slot < 0 || $assignments->isEmpty() || (! $repeat && $slot >= $assignments->count())) {
            return null;
        }
        $groups = [];
        foreach ($assignments->sortBy([['priority', 'asc'], ['id', 'asc']]) as $assignment) {
            $banner = $assignment->banner;
            $key = $banner->campaign_id === null ? 'banner:'.$banner->id : 'campaign:'.$banner->campaign_id;
            $groups[$key]['weight'] = max(1, min(1000, (int) ($banner->campaign?->delivery_weight ?? 1)));
            $groups[$key]['items'][] = $assignment;
            $groups[$key]['offset'] = 0;
        }
        if (count($groups) === 1) {
            $items = reset($groups)['items'];

            return $items[$slot % count($items)];
        }
        $totalWeight = array_sum(array_column($groups, 'weight'));
        for ($index = 0; $index <= $slot; $index++) {
            $ticket = hexdec(substr(hash('sha256', $seed.'|'.$index), 0, 12)) % $totalWeight;
            foreach ($groups as $key => $group) {
                if ($ticket >= $group['weight']) {
                    $ticket -= $group['weight'];

                    continue;
                }
                $selected = $group['items'][$group['offset'] % count($group['items'])];
                $groups[$key]['offset']++;
                if (! $repeat && $groups[$key]['offset'] >= count($group['items'])) {
                    $totalWeight -= $group['weight'];
                    unset($groups[$key]);
                }
                break;
            }
        }

        return $selected;
    }
}
