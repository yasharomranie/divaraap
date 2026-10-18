<?php

declare(strict_types=1);

namespace Modules\Listing\Filament\Admin\Widgets;

use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Modules\Listing\Models\Listing;

class ListingOverview extends StatsOverviewWidget
{
    public static function canView(): bool
    {
        return (bool) auth()->user()?->hasRole('admin');
    }

    protected static ?int $sort = 1;

    protected function getHeading(): ?string
    {
        return __('listing::admin.overview');
    }

    protected function getStats(): array
    {
        $stats = Listing::overviewStats();

        $featuredRatio = __('listing::admin.featured_ratio', ['percent' => $stats['total'] > 0 ? number_format(($stats['featured'] / $stats['total']) * 100, 1) : '0.0']);

        return [
            Stat::make(__('listing::admin.total_listings'), number_format($stats['total']))
                ->description(__('listing::admin.all_listings_in_the_system'))
                ->icon('heroicon-o-clipboard-document-list')
                ->color('primary'),
            Stat::make(__('listing::admin.active_listings'), number_format($stats['active']))
                ->description(__('listing::admin.pending_count', ['count' => number_format($stats['pending'])]))
                ->descriptionIcon('heroicon-o-clock')
                ->icon('heroicon-o-check-circle')
                ->color('success'),
            Stat::make(__('listing::admin.created_today'), number_format($stats['created_today']))
                ->description(__('listing::admin.new_listings_added_today'))
                ->icon('heroicon-o-calendar-days')
                ->color('info'),
            Stat::make(__('listing::admin.featured_listings'), number_format($stats['featured']))
                ->description($featuredRatio)
                ->icon('heroicon-o-star')
                ->color('warning'),
        ];
    }
}
