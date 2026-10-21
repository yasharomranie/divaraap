<?php

declare(strict_types=1);

namespace Modules\Promotion\Filament\Admin\Resources;

use BackedEnum;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Modules\Admin\Support\Filament\ResourceTableActions;
use Modules\Advertising\Filament\Admin\Resources\AdvertisingResource;
use Modules\Advertising\Models\AdvertisingInvoice;
use Modules\Promotion\Filament\Admin\Resources\SellerPackageResource\Pages;
use Modules\Promotion\Models\SellerPackage;

final class SellerPackageResource extends AdvertisingResource
{
    protected static ?string $model = SellerPackage::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-gift';

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
        return __('promotion::packages.package');
    }

    public static function getPluralModelLabel(): string
    {
        return __('promotion::packages.title');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->schema([
            TextInput::make('name')->label(__('promotion::admin.name'))->required()->maxLength(80),
            Textarea::make('description')->label(__('promotion::admin.description'))->maxLength(500),
            Select::make('promotion_plan_id')->label(__('promotion::admin.promotion_plan'))->options(fn (): array => SellerPackage::planOptions())->required(),
            TextInput::make('price')->label(__('advertising::billing.amount'))->integer()->minValue(1)->maxValue(1000000000000)->required(),
            TextInput::make('credits')->label(__('promotion::packages.credits'))->integer()->minValue(1)->maxValue(1000)->required(),
            TextInput::make('validity_days')->label(__('promotion::packages.validity'))->integer()->minValue(1)->maxValue(365)->required(),
            Toggle::make('is_active')->label(__('promotion::admin.is_active'))->default(false)->helperText(__('promotion::packages.catalog_hint')),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('name')->label(__('promotion::admin.name'))->searchable(),
            TextColumn::make('price')->label(__('advertising::billing.amount'))->numeric(),
            TextColumn::make('credits')->label(__('promotion::packages.credits'))->numeric(),
            TextColumn::make('validity_days')->label(__('promotion::packages.validity'))->numeric(),
            TextColumn::make('is_active')->label(__('promotion::admin.is_active'))->formatStateUsing(fn ($state) => $state ? __('promotion::packages.active') : __('promotion::packages.inactive')),
        ])->actions(ResourceTableActions::editDelete())->defaultSort('id', 'desc');
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ListPackages::route('/'), 'create' => Pages\CreatePackage::route('/create'), 'edit' => Pages\EditPackage::route('/{record}/edit')];
    }
}
