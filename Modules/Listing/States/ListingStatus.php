<?php

declare(strict_types=1);

namespace Modules\Listing\States;

use Spatie\ModelStates\State;
use Spatie\ModelStates\StateConfig;

abstract class ListingStatus extends State
{
    public static function options(): array
    {
        return [
            'active' => __('listing::admin.active'),
            'pending' => __('listing::admin.pending'),
            'sold' => __('listing::admin.sold'),
            'expired' => __('listing::admin.expired'),
        ];
    }

    public static function config(): StateConfig
    {
        return parent::config()
            ->default(ActiveListingStatus::class)
            ->allowAllTransitions()
            ->ignoreSameState();
    }
}
