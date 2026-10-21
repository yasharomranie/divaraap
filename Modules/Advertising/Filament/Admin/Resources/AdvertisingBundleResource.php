<?php

declare(strict_types=1);

namespace Modules\Advertising\Filament\Admin\Resources;

use BackedEnum;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Modules\Admin\Support\Filament\ResourceTableActions;
use Modules\Advertising\Filament\Admin\Resources\AdvertisingBundleResource\Pages;
use Modules\Advertising\Models\AdvertisingBundle;
use Modules\Advertising\Models\AdvertisingInvoice;
use Modules\Advertising\Models\AdvertisingOrder;

final class AdvertisingBundleResource extends AdvertisingResource
{
    protected static ?string $model = AdvertisingBundle::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-rectangle-stack';

    public static function canViewAny(): bool
    {
        return AdvertisingInvoice::billingAdmin();
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function getModelLabel(): string
    {
        return __('advertising::bundles.bundle');
    }

    public static function getPluralModelLabel(): string
    {
        return __('advertising::bundles.title');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->schema([
            TextInput::make('name')->label(__('advertising::admin.title'))->required()->maxLength(120),
            Textarea::make('description')->label(__('advertising::orders.brief'))->maxLength(500),
            TextInput::make('price')->label(__('advertising::billing.amount'))->integer()->minValue(1)->maxValue(1000000000000)->required(),
            Repeater::make('items')->label(__('advertising::bundles.items'))->minItems(2)->maxItems(6)->defaultItems(2)->required()->reorderableWithButtons()->schema([
                Select::make('placement')->label(__('advertising::admin.placement'))->options(fn (): array => AdvertisingOrder::placementOptions())->required()->disableOptionsWhenSelectedInSiblingRepeaterItems(),
                Select::make('device')->label(__('advertising::admin.device'))->options(collect(['both', 'mobile', 'desktop'])->mapWithKeys(fn ($device): array => [$device => __('advertising::admin.'.$device)]))->default('both')->required(),
                TextInput::make('duration_days')->label(__('advertising::orders.duration'))->integer()->minValue(1)->maxValue(90)->required(),
            ])->columnSpanFull(),
            Toggle::make('is_active')->label(__('advertising::admin.is_active'))->default(false)->helperText(__('advertising::bundles.catalog_hint')),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('name')->label(__('advertising::admin.title'))->searchable(),
            TextColumn::make('price')->label(__('advertising::billing.amount'))->numeric(),
            TextColumn::make('parts')->label(__('advertising::bundles.items'))->state(fn (AdvertisingBundle $record): int => count($record->items))->numeric(),
            TextColumn::make('is_active')->label(__('advertising::admin.is_active'))->formatStateUsing(fn ($state) => $state ? __('promotion::packages.active') : __('promotion::packages.inactive')),
        ])->actions(ResourceTableActions::editDelete())->defaultSort('id', 'desc');
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ListBundles::route('/'), 'create' => Pages\CreateBundle::route('/create'), 'edit' => Pages\EditBundle::route('/{record}/edit')];
    }
}
