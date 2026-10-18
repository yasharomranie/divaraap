<?php

declare(strict_types=1);

namespace Modules\Advertising\Filament\Admin\Pages;

use App\Support\Filament\HasAccessControlledPage;
use BackedEnum;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Validator;
use Livewire\Attributes\Computed;
use Modules\Advertising\Http\Requests\AdvertisingReportRequest;
use Modules\Advertising\Models\AdvertisingEvent;
use Modules\Advertising\Models\Campaign;
use UnitEnum;

final class AdvertisingStatistics extends Page
{
    use HasAccessControlledPage;

    protected string $view = 'advertising::statistics';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-chart-bar';

    public string $campaignId = '';

    public string $dimension = 'event_date';

    public string $fromDate = '';

    public string $toDate = '';

    public function mount(): void
    {
        $this->fromDate = now()->subDays(29)->toDateString();
        $this->toDate = now()->toDateString();
    }

    public function updated(string $property): void
    {
        if (in_array($property, ['campaignId', 'dimension', 'fromDate', 'toDate'], true)) {
            $this->validate(AdvertisingReportRequest::filterRules());
        }
    }

    protected static function getAccessPermission(): string
    {
        return 'advertising.manage';
    }

    public static function getNavigationLabel(): string
    {
        return __('advertising::metrics.title');
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
    public function campaigns(): array
    {
        abort_unless(self::canAccess(), 403);

        return Campaign::reportOptions();
    }

    #[Computed]
    public function rows(): array
    {
        abort_unless(self::canAccess(), 403);
        if (Validator::make(['campaignId' => $this->campaignId, 'dimension' => $this->dimension, 'fromDate' => $this->fromDate, 'toDate' => $this->toDate], AdvertisingReportRequest::filterRules())->fails()) {
            return [];
        }

        return AdvertisingEvent::report($this->campaignId === '' ? null : (int) $this->campaignId, $this->dimension, $this->fromDate, $this->toDate);
    }
}
