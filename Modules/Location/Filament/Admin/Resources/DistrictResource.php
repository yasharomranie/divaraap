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
use Modules\Location\Filament\Admin\Resources\DistrictResource\Pages;
use Modules\Location\Models\Country;
use Modules\Location\Models\District;
use UnitEnum;

class DistrictResource extends Resource
{
    protected static ?string $model = District::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-map';

    protected static string|UnitEnum|null $navigationGroup = 'Location';

    protected static ?string $label = 'District';

    protected static ?string $pluralLabel = 'Districts';

    protected static ?int $navigationSort = 4;

    public static function getModelLabel(): string
    {
        return __('location::admin.district');
    }

    public static function getPluralModelLabel(): string
    {
        return __('location::admin.districts');
    }

    public static function getNavigationLabel(): string
    {
        return __('location::admin.districts');
    }

    public static function getNavigationGroup(): string|UnitEnum|null
    {
        return __('location::admin.location');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->schema([
            TextInput::make('name')->label(__('location::admin.name'))->extraInputAttributes(['dir' => 'auto'])->required()->maxLength(120),
            Select::make('city_id')->label(__('location::admin.city'))->relationship('city', 'name')->label(__('location::admin.city'))->searchable()->preload()->required(),
            Toggle::make('is_active')->label(__('location::admin.is_active'))->default(true),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            ResourceTableColumns::id(),
            TextColumn::make('name')->label(__('location::admin.name'))->searchable()->sortable(),
            TextColumn::make('city.name')->label(__('location::admin.city'))->searchable()->sortable(),
            TextColumn::make('city.country.name')->label(__('location::admin.country')),
            ResourceTableColumns::activeIcon(),
            ResourceTableColumns::createdAtHidden(),
        ])->defaultSort('id', 'desc')->filters([
            SelectFilter::make('country_id')->label(__('location::admin.country'))
                ->options(fn (): array => Country::idNameOptions())
                ->query(fn (Builder $query, array $data): Builder => $query->when($data['value'] ?? null, fn (Builder $query, string $countryId): Builder => $query->whereHas('city', fn (Builder $cityQuery): Builder => $cityQuery->where('country_id', $countryId)))),
            SelectFilter::make('city_id')->label(__('location::admin.city'))
                ->relationship('city', 'name')
                ->searchable()
                ->preload(),
            TernaryFilter::make('is_active')->label(__('location::admin.active')),
        ])->actions(ResourceTableActions::editActivityDelete(static::class));
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListDistricts::route('/'),
            'create' => Pages\CreateDistrict::route('/create'),
            'activities' => Pages\ListDistrictActivities::route('/{record}/activities'),
            'edit' => Pages\EditDistrict::route('/{record}/edit'),
        ];
    }
}
