<?php

declare(strict_types=1);

namespace Modules\Admin\Filament\Pages;

use App\Support\Filament\HasAccessControlledPage;
use BackedEnum;
use Filament\Pages\Page;
use Livewire\Attributes\Computed;
use Modules\Listing\Models\Listing;
use UnitEnum;

final class ListingRiskReview extends Page
{
    use HasAccessControlledPage;

    protected string $view = 'admin::listing-risk-review';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-shield-exclamation';

    protected static function getAccessPermission(): string
    {
        return 'moderation.listings';
    }

    public static function getNavigationLabel(): string
    {
        return __('listing::risk.title');
    }

    public static function getNavigationGroup(): string|UnitEnum|null
    {
        return __('admin::operations.group');
    }

    public function getTitle(): string
    {
        return __('listing::risk.title');
    }

    #[Computed]
    public function snapshot(): array
    {
        abort_unless(self::canAccess(), 403);

        return Listing::riskReviewSnapshot();
    }

    public function refreshSignals(): void
    {
        abort_unless(self::canAccess(), 403);
        unset($this->snapshot);
    }
}
