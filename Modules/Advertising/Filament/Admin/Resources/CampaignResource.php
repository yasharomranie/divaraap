<?php

declare(strict_types=1);

namespace Modules\Advertising\Filament\Admin\Resources;

use BackedEnum;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Modules\Admin\Support\Filament\ResourceTableActions;
use Modules\Advertising\Filament\Admin\Resources\CampaignResource\Pages;
use Modules\Advertising\Models\Campaign;
use Modules\Category\Models\Category;
use Modules\Location\Models\City;

final class CampaignResource extends AdvertisingResource
{
    protected static ?string $model = Campaign::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-megaphone';

    public static function getModelLabel(): string
    {
        return __('advertising::campaign.campaign');
    }

    public static function getPluralModelLabel(): string
    {
        return __('advertising::campaign.campaigns');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->schema([
            TextInput::make('title')->label(__('advertising::admin.title'))->required()->maxLength(150),
            Select::make('goal')->label(__('advertising::campaign.goal'))->options(collect(['awareness', 'traffic', 'conversation'])->mapWithKeys(fn ($goal) => [$goal => __('advertising::campaign.'.$goal)])->all())->required()->default('awareness'),
            TextInput::make('delivery_weight')->label(__('advertising::metrics.delivery_weight'))->integer()->minValue(1)->maxValue(1000)->required()->default(1)->helperText(__('advertising::metrics.delivery_weight_hint')),
            TextInput::make('daily_frequency_cap')->label(__('advertising::metrics.daily_cap'))->integer()->minValue(1)->maxValue(10000)->helperText(__('advertising::metrics.daily_cap_hint')),
            Toggle::make('is_active')->label(__('advertising::admin.is_active'))->default(false),
            TextInput::make('planned_budget')->label(__('advertising::campaign.planned_budget'))->integer()->minValue(0)->maxValue(1000000000000)->helperText(__('advertising::campaign.budget_hint')),
            DateTimePicker::make('starts_at')->label(__('advertising::admin.starts_at'))->timezone(config('app.timezone'))->seconds(false),
            DateTimePicker::make('ends_at')->label(__('advertising::admin.ends_at'))->timezone(config('app.timezone'))->seconds(false)->after('starts_at'),
            Select::make('category_ids')->label(__('advertising::campaign.categories'))->multiple()->searchable()->options(fn () => Category::activeIdNameOptions())->helperText(__('advertising::campaign.category_hint')),
            Select::make('city_ids')->label(__('advertising::campaign.cities'))->multiple()->searchable()->options(fn () => City::advertisingOptions())->helperText(__('advertising::campaign.city_hint')),
            Repeater::make('page_paths')->label(__('advertising::campaign.pages'))->simple(TextInput::make('path')->required()->maxLength(500)->regex('~^/(?!/)[^?#\s]*$~')->extraInputAttributes(['dir' => 'ltr']))->defaultItems(0)->helperText(__('advertising::campaign.pages_hint'))->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('title')->label(__('advertising::admin.title'))->searchable()->sortable(),
            TextColumn::make('goal')->label(__('advertising::campaign.goal'))->formatStateUsing(fn ($state) => __('advertising::campaign.'.$state)),
            IconColumn::make('is_active')->label(__('advertising::admin.is_active'))->boolean(),
            TextColumn::make('banners_count')->counts('banners')->label(__('advertising::admin.banners')),
            TextColumn::make('delivery_weight')->label(__('advertising::metrics.delivery_weight'))->numeric(),
            TextColumn::make('planned_budget')->label(__('advertising::campaign.planned_budget'))->numeric(),
            TextColumn::make('starts_at')->label(__('advertising::admin.starts_at'))->dateTime()->timezone(config('app.timezone')),
            TextColumn::make('ends_at')->label(__('advertising::admin.ends_at'))->dateTime()->timezone(config('app.timezone')),
        ])->actions(ResourceTableActions::editDelete())->defaultSort('id', 'desc');
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ListCampaigns::route('/'), 'create' => Pages\CreateCampaign::route('/create'), 'edit' => Pages\EditCampaign::route('/{record}/edit')];
    }
}
