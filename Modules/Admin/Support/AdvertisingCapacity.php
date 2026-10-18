<?php

declare(strict_types=1);

namespace Modules\Admin\Support;

use Modules\Admin\App\Settings\OperationsSettings;

final class AdvertisingCapacity
{
    private array $reserved = [];

    private array $lastPositions = [];

    public function reserve(string $key, int $position): bool
    {
        $id = $key.':'.$position;
        if (isset($this->reserved[$id])) {
            return true;
        }
        $settings = app(OperationsSettings::class);
        if (count($this->reserved) >= $settings->max_ads_per_page) {
            return false;
        }
        if ($position > 0 && isset($this->lastPositions[$key]) && $position - $this->lastPositions[$key] < $settings->min_listing_gap) {
            return false;
        }
        $this->reserved[$id] = true;
        $this->lastPositions[$key] = $position;

        return true;
    }
}
