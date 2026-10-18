<?php

declare(strict_types=1);

namespace Modules\Advertising\Filament\Admin\Pages;

use App\Support\Filament\HasAccessControlledPage;
use BackedEnum;
use Filament\Pages\Page;
use Livewire\Attributes\Computed;
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

        return Campaign::options();
    }

    #[Computed]
    public function rows(): array
    {
        abort_unless(self::canAccess(), 403);
        $this->validate(['campaignId' => ['nullable', 'integer', 'min:1'], 'dimension' => ['required', 'in:event_date,banner_id,placement,device']]);

        return AdvertisingEvent::report($this->campaignId === '' ? null : (int) $this->campaignId, $this->dimension);
    }
}
