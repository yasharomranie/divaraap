<?php

declare(strict_types=1);

namespace Modules\Admin\Filament\Pages;

use BackedEnum;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Pages\SettingsPage;
use Filament\Schemas\Schema;
use UnitEnum;

final class OperationsSettings extends SettingsPage
{
    protected static string $settings = \Modules\Admin\App\Settings\OperationsSettings::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-adjustments-horizontal';

    public static function canAccess(): bool
    {
        return (bool) auth()->user()?->hasRole('admin');
    }

    public function canEdit(): bool
    {
        return self::canAccess();
    }

    public static function getNavigationLabel(): string
    {
        return __('admin::operations.settings');
    }

    public static function getNavigationGroup(): string|UnitEnum|null
    {
        return __('admin::operations.group');
    }

    public function getTitle(): string
    {
        return __('admin::operations.settings');
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('max_ads_per_page')->label(__('admin::operations.max_ads'))->integer()->minValue(0)->maxValue(100)->required(),
            TextInput::make('min_listing_gap')->label(__('admin::operations.min_gap'))->integer()->minValue(1)->maxValue(1000)->required(),
            Toggle::make('telemetry_enabled')->label(__('admin::operations.telemetry'))->helperText(__('admin::operations.privacy')),
        ]);
    }
}
