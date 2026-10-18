<?php

declare(strict_types=1);

namespace Modules\Advertising\Filament\Admin\Resources;

use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Modules\Advertising\Filament\Admin\Resources\BannerPlacementResource\Pages;
use Modules\Advertising\Models\BannerPlacement;

final class BannerPlacementResource extends AdvertisingResource
{
    protected static ?string $model = BannerPlacement::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-squares-2x2';

    public static function getModelLabel(): string
    {
        return __('advertising::admin.placement');
    }

    public static function getPluralModelLabel(): string
    {
        return __('advertising::admin.placements');
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->schema([
            Select::make('key')->label(__('advertising::admin.placement'))->options(collect(BannerPlacement::KEYS)->mapWithKeys(fn ($key) => [$key => __('advertising::admin.'.$key)]))->disabled()->dehydrated(false),
            Toggle::make('is_active')->label(__('advertising::admin.is_active')),
            Select::make('device')->label(__('advertising::admin.device'))->options(['both' => __('advertising::admin.both'), 'mobile' => __('advertising::admin.mobile'), 'desktop' => __('advertising::admin.desktop')])->required(),
            TextInput::make('first_after')->label(__('advertising::admin.first_after'))->integer()->minValue(1)->maxValue(10000)->required()->visible(fn ($record) => str_ends_with($record?->key ?? '', '-feed')),
            TextInput::make('interval')->label(__('advertising::admin.interval'))->integer()->minValue(1)->maxValue(10000)->required()->visible(fn ($record) => str_ends_with($record?->key ?? '', '-feed')),
            Select::make('ordering')->label(__('advertising::admin.ordering'))->options(['priority' => __('advertising::admin.priority'), 'random' => __('advertising::admin.random'), 'campaign' => __('advertising::metrics.campaign_ordering')])->required()->helperText(__('advertising::metrics.campaign_ordering_hint')),
            Toggle::make('repeat')->label(__('advertising::admin.repeat'))->helperText(__('advertising::admin.repeat_hint'))->visible(fn ($record) => str_ends_with($record?->key ?? '', '-feed')),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('key')->label(__('advertising::admin.placement'))->formatStateUsing(fn ($state) => __('advertising::admin.'.$state)),
            IconColumn::make('is_active')->label(__('advertising::admin.is_active'))->boolean(),
            TextColumn::make('assignments_count')->counts('assignments')->label(__('advertising::admin.banners')),
            TextColumn::make('ordering')->label(__('advertising::admin.ordering'))->formatStateUsing(fn ($state) => $state === 'campaign' ? __('advertising::metrics.campaign_ordering') : __('advertising::admin.'.$state)),
            TextColumn::make('first_after')->label(__('advertising::admin.first_after')),
            TextColumn::make('interval')->label(__('advertising::admin.interval')),
        ])->actions([EditAction::make()]);
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ListPlacements::route('/'), 'edit' => Pages\EditPlacement::route('/{record}/edit')];
    }
}
