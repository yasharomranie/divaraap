<?php

declare(strict_types=1);

namespace Modules\Advertising\Filament\Admin\Pages;

use App\Support\Filament\HasAccessControlledPage;
use BackedEnum;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Modules\Advertising\Models\AdvertisingWarning;
use UnitEnum;

final class AdvertisingWarnings extends Page
{
    use HasAccessControlledPage;

    protected string $view = 'advertising::warnings';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-exclamation-triangle';

    #[Locked]
    public ?string $checkedAt = null;

    #[Locked]
    public bool $scanFailed = false;

    protected static function getAccessPermission(): string
    {
        return 'advertising.manage';
    }

    public static function getNavigationLabel(): string
    {
        return __('advertising::warnings.title');
    }

    public static function getNavigationGroup(): string|UnitEnum|null
    {
        return __('advertising::admin.advertising');
    }

    public function getTitle(): string
    {
        return self::getNavigationLabel();
    }

    public function mount(): void
    {
        $this->scan(false);
    }

    public function scan(bool $feedback = true): void
    {
        abort_unless(self::canAccess(), 403);
        try {
            AdvertisingWarning::scan(auth()->user());
            $this->checkedAt = now()->toIso8601String();
            $this->scanFailed = false;
            unset($this->warnings);
            if ($feedback) {
                Notification::make()->title(__('advertising::warnings.checked'))->success()->send();
            }
        } catch (\Throwable $exception) {
            $this->scanFailed = true;
            report($exception);
            Notification::make()->title(__('advertising::warnings.failed'))->danger()->send();
        }
    }

    #[Computed]
    public function warnings(): Collection
    {
        abort_unless(self::canAccess(), 403);

        return AdvertisingWarning::forUser((int) auth()->id());
    }
}
