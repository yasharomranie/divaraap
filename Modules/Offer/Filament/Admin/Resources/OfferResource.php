<?php

declare(strict_types=1);

namespace Modules\Offer\Filament\Admin\Resources;

use App\Support\Filament\CurrencySelect;
use App\Support\Filament\MoneyInput;
use BackedEnum;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Modules\Admin\Support\Filament\AccessControlledResource as Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Modules\Admin\Support\Filament\ResourceTableActions;
use Modules\Admin\Support\Filament\ResourceTableColumns;
use Modules\Offer\Filament\Admin\Resources\OfferResource\Pages;
use Modules\Offer\Models\Offer;
use UnitEnum;

class OfferResource extends Resource
{
    protected static ?string $model = Offer::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-arrows-right-left';

    protected static string|UnitEnum|null $navigationGroup = 'Marketplace';

    protected static ?string $label = 'Offer';

    protected static ?string $pluralLabel = 'Offers';

    protected static ?int $navigationSort = 3;

    public static function getModelLabel(): string
    {
        return __('offer::admin.offer');
    }

    public static function getPluralModelLabel(): string
    {
        return __('offer::admin.offers');
    }

    public static function getNavigationLabel(): string
    {
        return __('offer::admin.offers');
    }

    public static function getNavigationGroup(): string|UnitEnum|null
    {
        return __('offer::admin.marketplace');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->schema([
            TextInput::make('listing_id')->label(__('offer::admin.listing'))->extraInputAttributes(['dir' => 'auto'])->numeric()->required(),
            TextInput::make('buyer_id')->label(__('offer::admin.buyer'))->extraInputAttributes(['dir' => 'auto'])->numeric()->required(),
            TextInput::make('seller_id')->label(__('offer::admin.seller'))->extraInputAttributes(['dir' => 'auto'])->numeric()->required(),
            MoneyInput::make('amount')->label(__('offer::admin.amount'))->required(),
            CurrencySelect::make()->label(__('offer::admin.currency')),
            Textarea::make('message')->label(__('offer::admin.message'))->rows(3)->maxLength(500),
            Select::make('status')->label(__('offer::admin.status'))->options(fn (): array => collect(Offer::statuses())->mapWithKeys(
                static fn (string $status): array => [$status => __('offer::messages.status_'.$status)]
            )->all())->required(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            ResourceTableColumns::id(),
            TextColumn::make('listing_id')->label(__('offer::admin.listing'))->sortable(),
            TextColumn::make('amount')->label(__('offer::admin.amount'))->formatStateUsing(fn (Offer $record): string => $record->amountLabel())->sortable(),
            TextColumn::make('status')->label(__('offer::admin.status'))->formatStateUsing(fn (string $state): string => __('offer::messages.status_'.$state))->badge()->sortable(),
            TextColumn::make('buyer_id')->label(__('offer::admin.buyer')),
            TextColumn::make('seller_id')->label(__('offer::admin.seller')),
            ResourceTableColumns::createdAtHidden(),
        ])->defaultSort('id', 'desc')->filters([
            SelectFilter::make('status')->label(__('offer::admin.status'))->options(fn (): array => collect(Offer::statuses())->mapWithKeys(
                static fn (string $status): array => [$status => __('offer::messages.status_'.$status)]
            )->all()),
        ])->actions(ResourceTableActions::editDelete());
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListOfferResourceRecords::route('/'),
            'create' => Pages\CreateOfferResourceRecord::route('/create'),
            'edit' => Pages\EditOfferResourceRecord::route('/{record}/edit'),
        ];
    }
}
