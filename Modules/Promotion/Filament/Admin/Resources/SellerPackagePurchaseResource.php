<?php

declare(strict_types=1);

namespace Modules\Promotion\Filament\Admin\Resources;

use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Modules\Advertising\Filament\Admin\Resources\AdvertisingResource;
use Modules\Advertising\Models\AdvertisingInvoice;
use Modules\Promotion\Filament\Admin\Resources\SellerPackagePurchaseResource\Pages;
use Modules\Promotion\Models\SellerPackagePurchase;

final class SellerPackagePurchaseResource extends AdvertisingResource
{
    protected static ?string $model = SellerPackagePurchase::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-ticket';

    public static function canViewAny(): bool
    {
        return AdvertisingInvoice::billingAdmin();
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit(Model $record): bool
    {
        return false;
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function getModelLabel(): string
    {
        return __('promotion::packages.purchase');
    }

    public static function getPluralModelLabel(): string
    {
        return __('promotion::packages.purchases');
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('id')->label(__('promotion::packages.number')),
            TextColumn::make('user_id')->label(__('promotion::packages.customer_id')),
            TextColumn::make('snapshot.name')->label(__('promotion::packages.package')),
            TextColumn::make('snapshot.price')->label(__('advertising::billing.amount'))->numeric(),
            TextColumn::make('used_credits')->label(__('promotion::packages.used'))->numeric(),
            TextColumn::make('status')->label(__('advertising::billing.state'))->state(fn (SellerPackagePurchase $record): string => __('promotion::packages.'.$record->displayState()))->badge(),
            TextColumn::make('ends_at')->label(__('promotion::packages.expires'))->dateTime()->timezone('Asia/Tehran'),
        ])->actions([
            Action::make('invoice')->label(__('promotion::packages.issue'))->requiresConfirmation()->visible(fn (SellerPackagePurchase $record): bool => $record->state === 'requested')->action(fn (SellerPackagePurchase $record) => $record->review('invoice')),
            Action::make('receipt')->label(__('advertising::billing.view'))->visible(fn (SellerPackagePurchase $record): bool => $record->invoice_id !== null)->url(fn (SellerPackagePurchase $record): string => route('advertising.invoices.show', $record->invoice_id ?? 0)),
            Action::make('activate')->label(__('promotion::packages.activate'))->requiresConfirmation()->modalDescription(__('promotion::packages.activate_hint'))->visible(fn (SellerPackagePurchase $record): bool => $record->state === 'invoiced')->action(fn (SellerPackagePurchase $record) => $record->review('activate')),
            Action::make('cancel')->label(__('promotion::packages.cancel'))->requiresConfirmation()->color('danger')->visible(fn (SellerPackagePurchase $record): bool => in_array($record->state, ['requested', 'invoiced'], true))
                ->schema([Textarea::make('reason')->label(__('advertising::billing.reason'))->required()->maxLength(500)])->action(fn (SellerPackagePurchase $record, array $data) => $record->review('cancel', $data['reason'])),
        ])->defaultSort('id', 'desc');
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ListPurchases::route('/')];
    }
}
