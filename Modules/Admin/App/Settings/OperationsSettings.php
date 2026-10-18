<?php

declare(strict_types=1);

namespace Modules\Admin\App\Settings;

use Spatie\LaravelSettings\Settings;

final class OperationsSettings extends Settings
{
    public int $max_ads_per_page;

    public int $min_listing_gap;

    public bool $telemetry_enabled;

    public static function group(): string
    {
        return 'operations';
    }
}
