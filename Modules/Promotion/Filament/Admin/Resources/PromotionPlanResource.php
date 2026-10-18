<?php

declare(strict_types=1);

namespace Modules\Promotion\Filament\Admin\Resources;

use App\Support\Filament\CurrencySelect;
use App\Support\Filament\MoneyInput;
use BackedEnum;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Modules\Admin\Support\Filament\AccessControlledResource as Resource;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Support\Str;
use Modules\Admin\Support\Filament\ResourceTableActions;
use Modules\Admin\Support\Filament\ResourceTableColumns;
use Modules\Promotion\Filament\Admin\Resources\PromotionPlanResource\Pages;
use Modules\Promotion\Models\PromotionPlan;
use UnitEnum;

class PromotionPlanResource extends Resource
{
    protected static ?string $model = PromotionPlan::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-sparkles';

    protected static string|UnitEnum|null $navigationGroup = 'Marketplace';

    protected static ?string $label = 'Promotion plan';

    protected static ?string $pluralLabel = 'Promotion plans';

    protected static ?int $navigationSort = 1;

    public static function getModelLabel(): string
    {
        return __('promotion::admin.promotion_plan');
    }

    public static function getPluralModelLabel(): string
    {
        return __('promotion::admin.promotion_plans');
    }

    public static function getNavigationLabel(): string
    {
        return __('promotion::admin.promotion_plans');
    }

    public static function getNavigationGroup(): string|UnitEnum|null
    {
        return __('promotion::admin.marketplace');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->schema([
            TextInput::make('name')->label(__('promotion::admin.name'))->extraInputAttributes(['dir' => 'auto'])->required()->maxLength(80)->live(onBlur: true)
                ->afterStateUpdated(static fn (Set $set, ?string $state): mixed => $set('slug', Str::slug((string) $state))),
            TextInput::make('slug')->label(__('promotion::admin.slug'))->extraInputAttributes(['dir' => 'ltr'])->required()->maxLength(80)->unique(ignoreRecord: true),
            Textarea::make('description')->label(__('promotion::admin.description'))->rows(3)->maxLength(500),
            MoneyInput::make('price')->label(__('promotion::admin.price'))->required()->default(0),
            CurrencySelect::make()->label(__('promotion::admin.currency')),
            TextInput::make('duration_days')->label(__('promotion::admin.duration_days'))->extraInputAttributes(['dir' => 'auto'])->numeric()->required()->default(7),
            TextInput::make('bump_count')->label(__('promotion::admin.bump_count'))->extraInputAttributes(['dir' => 'auto'])->numeric()->default(0),
            Toggle::make('grants_featured')->label(__('promotion::admin.grants_featured')),
            Toggle::make('grants_urgent')->label(__('promotion::admin.grants_urgent')),
            Toggle::make('is_active')->label(__('promotion::admin.is_active'))->default(true),
            TextInput::make('sort_order')->label(__('promotion::admin.sort_order'))->extraInputAttributes(['dir' => 'auto'])->numeric()->default(0),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            ResourceTableColumns::id(),
            TextColumn::make('name')->label(__('promotion::admin.name'))->searchable()->sortable(),
            TextColumn::make('price')->label(__('promotion::admin.price'))->formatStateUsing(fn (PromotionPlan $record): string => $record->priceLabel())->sortable(),
            TextColumn::make('duration_days')->label(__('promotion::admin.days'))->sortable(),
            IconColumn::make('grants_featured')->label(__('promotion::admin.grants_featured'))->boolean()->label(__('promotion::admin.featured')),
            IconColumn::make('grants_urgent')->label(__('promotion::admin.grants_urgent'))->boolean()->label(__('promotion::admin.urgent')),
            ResourceTableColumns::activeIcon(),
        ])->defaultSort('id', 'desc')->filters([
            TernaryFilter::make('is_active')->label(__('promotion::admin.active')),
            TernaryFilter::make('grants_featured')->label(__('promotion::admin.featured')),
        ])->actions(ResourceTableActions::editDelete());
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPromotionPlanResourceRecords::route('/'),
            'create' => Pages\CreatePromotionPlanResourceRecord::route('/create'),
            'edit' => Pages\EditPromotionPlanResourceRecord::route('/{record}/edit'),
        ];
    }
}
