<?php

declare(strict_types=1);

namespace Modules\Admin\Filament\Pages;

use BackedEnum;
use Filament\Pages\Page;
use Modules\Admin\Models\StaffSecurityEvent;
use UnitEnum;

final class StaffSecurityEvents extends Page
{
    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-finger-print';

    protected string $view = 'admin::staff-security.events';

    public static function canAccess(): bool
    {
        return ManageStaffSecurity::canAccess();
    }

    public static function getNavigationLabel(): string
    {
        return __('admin::security.events');
    }

    public static function getNavigationGroup(): string|UnitEnum|null
    {
        return __('admin::operations.group');
    }

    public function getTitle(): string
    {
        return self::getNavigationLabel();
    }

    protected function getViewData(): array
    {
        abort_unless(self::canAccess(), 403);

        return ['events' => StaffSecurityEvent::query()->latest('id')->paginate(25)];
    }
}
