<?php

declare(strict_types=1);

namespace Modules\Advertising\Filament\Admin\Resources;

use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Modules\Advertising\Filament\Admin\Resources\AdvertisingRefundRequestResource\Pages\ListAdvertisingRefundRequests;
use Modules\Advertising\Models\AdvertisingInvoice;
use Modules\Advertising\Models\AdvertisingRefundRequest;

final class AdvertisingRefundRequestResource extends AdvertisingResource
{
    protected static ?string $model = AdvertisingRefundRequest::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-arrow-uturn-left';

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
        return __('advertising::refunds.request');
    }

    public static function getPluralModelLabel(): string
    {
        return __('advertising::refunds.requests');
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('id')->label(__('advertising::refunds.request'))->numeric(),
            TextColumn::make('invoice_id')->label(__('advertising::billing.invoice'))->numeric(),
            TextColumn::make('payment_id')->label(__('advertising::refunds.payment'))->numeric(),
            TextColumn::make('amount')->label(__('advertising::billing.amount'))->numeric(),
            TextColumn::make('state')->label(__('advertising::billing.state'))->formatStateUsing(fn (string $state): string => __('advertising::refunds.'.$state))->badge(),
            TextColumn::make('reason')->label(__('advertising::refunds.reason'))->wrap()->limit(120),
            TextColumn::make('created_at')->label(__('advertising::refunds.requested_at'))->dateTime()->timezone('Asia/Tehran'),
        ])->filters([
            SelectFilter::make('state')->label(__('advertising::billing.state'))->options(collect(['requested', 'approved', 'completed', 'rejected', 'withdrawn', 'cancelled'])->mapWithKeys(fn (string $state): array => [$state => __('advertising::refunds.'.$state)])->all()),
        ])->recordActions([
            Action::make('invoice')->label(__('advertising::billing.view'))->url(fn (AdvertisingRefundRequest $record): string => route('advertising.invoices.show', $record->invoice_id)),
            Action::make('approve')->label(__('advertising::refunds.approve'))->visible(fn (AdvertisingRefundRequest $record): bool => $record->state === 'requested')->requiresConfirmation()->modalDescription(__('advertising::refunds.approval_help'))
                ->schema([Hidden::make('version')->default(fn (AdvertisingRefundRequest $record): int => $record->version)])
                ->action(function (AdvertisingRefundRequest $record, array $data): void {
                    $record->decide('approve', (int) $data['version']);
                    Notification::make()->success()->title(__('advertising::refunds.updated'))->send();
                }),
            Action::make('reject')->label(__('advertising::refunds.reject'))->visible(fn (AdvertisingRefundRequest $record): bool => $record->state === 'requested')->requiresConfirmation()
                ->schema([Hidden::make('version')->default(fn (AdvertisingRefundRequest $record): int => $record->version), Textarea::make('reason')->label(__('advertising::refunds.decision_reason'))->required()->maxLength(500)])
                ->action(function (AdvertisingRefundRequest $record, array $data): void {
                    $record->decide('reject', (int) $data['version'], $data['reason']);
                    Notification::make()->success()->title(__('advertising::refunds.updated'))->send();
                }),
            Action::make('complete')->label(__('advertising::refunds.complete'))->visible(fn (AdvertisingRefundRequest $record): bool => $record->state === 'approved')->requiresConfirmation()->modalDescription(fn (AdvertisingRefundRequest $record): string => __('advertising::refunds.manual_help').' '.__('advertising::refunds.approved_amount').': '.number_format($record->amount).' '.__('advertising::billing.rial'))
                ->schema([Hidden::make('version')->default(fn (AdvertisingRefundRequest $record): int => $record->version), TextInput::make('reference')->label(__('advertising::refunds.transfer_reference'))->required()->minLength(4)->maxLength(80)->regex('/\A[A-Za-z0-9][A-Za-z0-9_-]{3,79}\z/')->extraInputAttributes(['dir' => 'ltr']), Checkbox::make('confirmed')->label(__('advertising::refunds.confirm_transfer'))->accepted()])
                ->action(function (AdvertisingRefundRequest $record, array $data): void {
                    $record->complete($data['reference'], (int) $data['version'], (bool) ($data['confirmed'] ?? false));
                    Notification::make()->success()->title(__('advertising::refunds.updated'))->send();
                }),
            Action::make('cancel')->label(__('advertising::refunds.cancel_approval'))->visible(fn (AdvertisingRefundRequest $record): bool => $record->state === 'approved')->requiresConfirmation()
                ->schema([Hidden::make('version')->default(fn (AdvertisingRefundRequest $record): int => $record->version), Textarea::make('reason')->label(__('advertising::refunds.decision_reason'))->required()->maxLength(500), Checkbox::make('confirmed')->label(__('advertising::refunds.confirm_no_transfer'))->accepted()])
                ->action(function (AdvertisingRefundRequest $record, array $data): void {
                    $record->decide('cancel', (int) $data['version'], $data['reason'], (bool) ($data['confirmed'] ?? false));
                    Notification::make()->success()->title(__('advertising::refunds.updated'))->send();
                }),
        ])->defaultSort('id', 'desc');
    }

    public static function getPages(): array
    {
        return ['index' => ListAdvertisingRefundRequests::route('/')];
    }
}
