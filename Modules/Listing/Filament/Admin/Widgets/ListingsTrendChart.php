<?php

declare(strict_types=1);

namespace Modules\Listing\Filament\Admin\Widgets;

use Filament\Widgets\ChartWidget;
use Modules\Listing\Models\Listing;

class ListingsTrendChart extends ChartWidget
{
    public static function canView(): bool
    {
        return (bool) auth()->user()?->hasRole('admin');
    }

    protected static ?int $sort = 2;

    protected int|string|array $columnSpan = 'full';

    public function getHeading(): ?string
    {
        return __('listing::admin.trend');
    }

    public function getDescription(): ?string
    {
        return __('listing::admin.trend_description');
    }

    protected function getFilters(): ?array
    {
        return [
            '7' => __('listing::admin.last_7_days'),
            '30' => __('listing::admin.last_30_days'),
            '90' => __('listing::admin.last_90_days'),
        ];
    }

    protected function getData(): array
    {
        $days = (int) ($this->filter ?? '30');
        $trend = Listing::creationTrend($days);

        return [
            'datasets' => [
                [
                    'label' => __('listing::admin.listings'),
                    'data' => $trend['data'],
                    'fill' => true,
                    'borderColor' => '#2563eb',
                    'backgroundColor' => 'rgba(37, 99, 235, 0.12)',
                    'tension' => 0.35,
                ],
            ],
            'labels' => $trend['labels'],
        ];
    }

    protected function getType(): string
    {
        return 'line';
    }
}
