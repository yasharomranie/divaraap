<?php

declare(strict_types=1);

namespace Modules\Advertising\Filament\Admin\Resources;

use Illuminate\Database\Eloquent\Model;
use Modules\Admin\Support\Filament\AccessControlledResource as Resource;
use UnitEnum;

abstract class AdvertisingResource extends Resource
{
    public static function canViewAny(): bool
    {
        return (string) auth()->user()?->status === 'active' && (bool) auth()->user()?->can('advertising.manage');
    }

    public static function canCreate(): bool
    {
        return static::canViewAny();
    }

    public static function canEdit(Model $record): bool
    {
        return static::canViewAny();
    }

    public static function canDelete(Model $record): bool
    {
        return static::canViewAny();
    }

    public static function getNavigationGroup(): string|UnitEnum|null
    {
        return __('advertising::admin.advertising');
    }
}
