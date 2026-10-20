<?php

declare(strict_types=1);

namespace Modules\Advertising\Filament\Admin\Pages;

use App\Support\Filament\HasAccessControlledPage;
use BackedEnum;
use Filament\Pages\Page;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Modules\Advertising\Models\Banner;
use Modules\Advertising\Models\BannerPlacement;
use UnitEnum;

final class AdvertisingPreview extends Page
{
    use HasAccessControlledPage;

    protected string $view = 'advertising::preview';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-eye';

    #[Url(as: 'banner')]
    public string $bannerId = '';

    public string $device = 'mobile';

    #[Url]
    public string $placement = 'home-top';

    protected static function getAccessPermission(): string
    {
        return 'advertising.manage';
    }

    public static function getNavigationLabel(): string
    {
        return __('advertising::preview.title');
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
    public function banners(): array
    {
        abort_unless(self::canAccess(), 403);

        return Banner::previewOptions();
    }

    #[Computed]
    public function previewUrl(): ?string
    {
        abort_unless(self::canAccess(), 403);
        if ($this->bannerId === '') {
            return null;
        }
        $this->validate(['bannerId' => ['required', 'integer', 'min:1'], 'device' => ['required', 'in:mobile,desktop'], 'placement' => ['required', Rule::in(BannerPlacement::KEYS)]]);

        return route('advertising.preview-render', ['banner' => $this->bannerId, 'device' => $this->device, 'placement' => $this->placement]);
    }
}
