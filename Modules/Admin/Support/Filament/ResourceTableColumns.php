<?php

declare(strict_types=1);

namespace Modules\Admin\Support\Filament;

use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;

final class ResourceTableColumns
{
    public static function id(string $name = 'id'): TextColumn
    {
        return TextColumn::make($name)->label(__('admin::admin.id'))->sortable();
    }

    public static function activeIcon(string $name = 'is_active', string $label = 'Active'): IconColumn
    {
        return IconColumn::make($name)->label($label === 'Active' ? __('admin::admin.active') : $label)->boolean();
    }

    public static function createdAtHidden(string $name = 'created_at'): TextColumn
    {
        return TextColumn::make($name)->label(__('admin::admin.created_at'))
            ->dateTime()
            ->sortable()
            ->toggleable(isToggledHiddenByDefault: true);
    }
}
