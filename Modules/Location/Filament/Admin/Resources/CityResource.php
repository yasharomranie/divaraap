<?php

declare(strict_types=1);

namespace Modules\Location\Filament\Admin\Resources;

use BackedEnum;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Modules\Admin\Support\Filament\AccessControlledResource as Resource;
use Modules\Admin\Support\Filament\ResourceTableActions;
use Modules\Admin\Support\Filament\ResourceTableColumns;
use Modules\Location\Filament\Admin\Resources\CityResource\Pages;
use Modules\Location\Models\City;
use UnitEnum;

class CityResource extends Resource
{
    protected static ?string $model = City::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-building-office-2';

    protected static string|UnitEnum|null $navigationGroup = 'Location';

    protected static ?string $label = 'City';

    protected static ?string $pluralLabel = 'Cities';

    protected static ?int $navigationSort = 3;

    public static function getModelLabel(): string
    {
        return __('location::admin.city');
    }

    public static function getPluralModelLabel(): string
    {
        return __('location::admin.cities');
    }

    public static function getNavigationLabel(): string
    {
        return __('location::admin.cities');
    }

    public static function getNavigationGroup(): string|UnitEnum|null
    {
        return __('location::admin.location');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->schema([
            TextInput::make('name')->label(__('location::admin.name'))->extraInputAttributes(['dir' => 'auto'])->required()->maxLength(120),
            Select::make('country_id')->label(__('location::admin.country'))->relationship('country', 'name')->label(__('location::admin.country'))->searchable()->preload()->required(),
            Toggle::make('is_active')->label(__('location::admin.is_active'))->default(true),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            ResourceTableColumns::id(),
            TextColumn::make('name')->label(__('location::admin.name'))->searchable()->sortable(),
            TextColumn::make('country.name')->label(__('location::admin.country'))->searchable()->sortable(),
            TextColumn::make('districts_count')->label(__('location::admin.districts'))->counts('districts')->label(__('location::admin.districts'))->sortable(),
            ResourceTableColumns::activeIcon(),
            ResourceTableColumns::createdAtHidden(),
        ])->defaultSort('id', 'desc')->filters([
            SelectFilter::make('country_id')->label(__('location::admin.country'))
                ->relationship('country', 'name')
                ->searchable()
                ->preload(),
            TernaryFilter::make('has_districts')->label(__('location::admin.has_districts'))
                ->queries(
                    true: fn (Builder $query): Builder => $query->has('districts'),
                    false: fn (Builder $query): Builder => $query->doesntHave('districts'),
                    blank: fn (Builder $query): Builder => $query,
                ),
            TernaryFilter::make('is_active')->label(__('location::admin.active')),
        ])->actions(ResourceTableActions::editActivityDelete(static::class));
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListCities::route('/'),
            'create' => Pages\CreateCity::route('/create'),
            'activities' => Pages\ListCityActivities::route('/{record}/activities'),
            'edit' => Pages\EditCity::route('/{record}/edit'),
        ];
    }
}
