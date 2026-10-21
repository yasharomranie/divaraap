<?php

declare(strict_types=1);

namespace Modules\Advertising\Filament\Admin\Resources;

use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Modules\Advertising\Filament\Admin\Resources\AdvertisingInvoiceResource\Pages;
use Modules\Advertising\Models\AdvertisingInvoice;
use Modules\Advertising\Models\AdvertisingPayment;
use Modules\Advertising\Models\Campaign;

final class AdvertisingInvoiceResource extends AdvertisingResource
{
    protected static ?string $model = AdvertisingInvoice::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-document-currency-dollar';

    public static function canViewAny(): bool
    {
        return AdvertisingInvoice::billingAdmin();
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
        return __('advertising::billing.invoice');
    }

    public static function getPluralModelLabel(): string
    {
        return __('advertising::billing.invoices');
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->withSum('payments', 'amount');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->schema([
            Select::make('user_id')->label(__('advertising::billing.customer'))->required()->searchable()
                ->options(fn (): array => AdvertisingInvoice::customerOptions())
                ->getSearchResultsUsing(fn (string $search): array => AdvertisingInvoice::customerOptions($search))
                ->getOptionLabelUsing(fn ($value): ?string => AdvertisingInvoice::customerLabel((int) $value)),
            Select::make('campaign_id')->label(__('advertising::campaign.campaign'))->options(fn (): array => Campaign::options())->searchable()->helperText(__('advertising::billing.campaign_hint')),
            Textarea::make('description')->label(__('advertising::billing.description'))->required()->maxLength(500)->rows(3),
            TextInput::make('amount')->label(__('advertising::billing.amount'))->required()->integer()->minValue(1)->maxValue(1000000000000)->helperText(__('advertising::billing.issue_hint')),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('number')->label(__('advertising::billing.number'))->searchable()->copyable(),
            TextColumn::make('customer.name')->label(__('advertising::billing.customer')),
            TextColumn::make('amount')->label(__('advertising::billing.amount'))->numeric(),
            TextColumn::make('received')->label(__('advertising::billing.received'))->state(fn (AdvertisingInvoice $record): int => $record->paidAmount())->numeric(),
            TextColumn::make('balance')->label(__('advertising::billing.balance'))->state(fn (AdvertisingInvoice $record): int => $record->balance())->numeric(),
            TextColumn::make('state')->label(__('advertising::billing.state'))->state(fn (AdvertisingInvoice $record): string => __('advertising::billing.'.$record->billingState()))->badge(),
            TextColumn::make('created_at')->label(__('advertising::billing.issued_at'))->dateTime()->timezone('Asia/Tehran'),
        ])->actions([
            Action::make('receipt')->label(__('advertising::billing.view'))->url(fn (AdvertisingInvoice $record): string => route('advertising.invoices.show', $record)),
            Action::make('receive')->label(__('advertising::billing.record_payment'))->visible(fn (AdvertisingInvoice $record): bool => $record->balance() > 0 && ! $record->voided_at)
                ->requiresConfirmation()->modalDescription(__('advertising::billing.payment_warning'))
                ->schema([
                    TextInput::make('amount')->label(__('advertising::billing.amount'))->integer()->minValue(1)->maxValue(fn (AdvertisingInvoice $record): int => $record->balance())->required(),
                    TextInput::make('reference')->label(__('advertising::billing.reference'))->required()->minLength(4)->maxLength(80)->regex('/^[A-Za-z0-9][A-Za-z0-9_-]{3,79}$/D')->extraInputAttributes(['dir' => 'ltr']),
                    Textarea::make('note')->label(__('advertising::billing.note'))->maxLength(500),
                ])->action(function (AdvertisingInvoice $record, array $data): void {
                    AdvertisingPayment::recordReceived($record, (string) $data['amount'], $data['reference'], $data['note'] ?? null);
                    Notification::make()->success()->title(__('advertising::billing.payment_saved'))->send();
                }),
            Action::make('void')->label(__('advertising::billing.void_action'))->color('danger')->requiresConfirmation()
                ->visible(fn (AdvertisingInvoice $record): bool => ! $record->voided_at && $record->paidAmount() === 0)
                ->schema([Textarea::make('reason')->label(__('advertising::billing.reason'))->required()->maxLength(500)])
                ->action(fn (AdvertisingInvoice $record, array $data) => $record->voidInvoice($data['reason'])),
        ])->defaultSort('id', 'desc');
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ListAdvertisingInvoices::route('/'), 'create' => Pages\CreateAdvertisingInvoice::route('/create')];
    }
}
