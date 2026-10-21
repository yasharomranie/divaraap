<?php

declare(strict_types=1);

namespace Modules\Advertising\Filament\Admin\Pages;

use BackedEnum;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Validator;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Modules\Advertising\Models\AdvertisingInvoice;
use Modules\Advertising\Models\PlacementReservation;
use UnitEnum;

final class PlacementCalendar extends Page
{
    protected string $view = 'advertising::placement-calendar';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-calendar';

    #[Url]
    public string $month = '';

    #[Url]
    public string $placementId = '';

    public static function canAccess(): bool
    {
        return AdvertisingInvoice::billingAdmin();
    }

    public function mount(): void
    {
        abort_unless(self::canAccess(), 403);
        if ($this->month === '') {
            $this->month = now('Asia/Tehran')->format('Y-m');
        }
    }

    public static function getNavigationLabel(): string
    {
        return __('advertising::reservations.calendar');
    }

    public static function getNavigationGroup(): string|UnitEnum|null
    {
        return __('advertising::admin.advertising');
    }

    public function getTitle(): string
    {
        return self::getNavigationLabel();
    }

    #[Computed]
    public function calendar(): array
    {
        abort_unless(self::canAccess(), 403);
        if (Validator::make(['month' => $this->month, 'placementId' => $this->placementId], ['month' => ['required', 'date_format:Y-m', 'regex:/^20[0-9]{2}-(0[1-9]|1[0-2])$/D'], 'placementId' => ['nullable', 'integer', 'min:1']])->fails()) {
            return ['days' => [], 'limited' => false];
        }

        return PlacementReservation::calendar($this->month, $this->placementId);
    }

    #[Computed]
    public function placements(): array
    {
        abort_unless(self::canAccess(), 403);

        return PlacementReservation::placements();
    }
}
