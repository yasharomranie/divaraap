<?php

declare(strict_types=1);

namespace Modules\Advertising\Filament\Admin\Resources;

use BackedEnum;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Modules\Advertising\Filament\Admin\Resources\OnlinePaymentAttemptResource\Pages\ListOnlinePaymentAttempts;
use Modules\Advertising\Models\AdvertisingInvoice;
use Modules\Advertising\Models\OnlinePaymentAttempt;

final class OnlinePaymentAttemptResource extends AdvertisingResource
{
    protected static ?string $model = OnlinePaymentAttempt::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-credit-card';

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
        return __('advertising::gateway.attempt');
    }

    public static function getPluralModelLabel(): string
    {
        return __('advertising::gateway.attempts');
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('id')->label(__('advertising::gateway.attempt'))->searchable()->copyable(),
            TextColumn::make('invoice_id')->label(__('advertising::billing.invoice'))->numeric(),
            TextColumn::make('gateway')->label(__('advertising::gateway.gateway'))->formatStateUsing(fn (string $state): string => __('advertising::gateway.'.$state)),
            TextColumn::make('sandbox')->label(__('advertising::gateway.sandbox'))->formatStateUsing(fn (bool $state): string => __('advertising::gateway.'.($state ? 'test_mode' : 'live_mode'))),
            TextColumn::make('amount')->label(__('advertising::billing.amount'))->numeric(),
            TextColumn::make('status')->label(__('advertising::billing.state'))->formatStateUsing(fn (string $state): string => __('advertising::gateway.'.$state))->badge(),
            TextColumn::make('gateway_reference')->label(__('advertising::billing.reference')),
            TextColumn::make('response_code')->label(__('advertising::gateway.response_code')),
            TextColumn::make('created_at')->label(__('advertising::billing.recorded_at'))->dateTime()->timezone('Asia/Tehran'),
        ])->filters([
            SelectFilter::make('gateway')->label(__('advertising::gateway.gateway'))->options(['zarinpal' => __('advertising::gateway.zarinpal'), 'zibal' => __('advertising::gateway.zibal')]),
            SelectFilter::make('status')->label(__('advertising::billing.state'))->options(collect(['creating', 'pending', 'verified', 'failed', 'needs_review', 'test_passed'])->mapWithKeys(fn (string $state): array => [$state => __('advertising::gateway.'.$state)])->all()),
        ])->recordActions([
            Action::make('check')->label(__('advertising::gateway.check'))->requiresConfirmation()->visible(fn (OnlinePaymentAttempt $record): bool => ! in_array($record->status, ['verified', 'test_passed'], true))
                ->action(function (OnlinePaymentAttempt $record): void {
                    abort_unless(AdvertisingInvoice::billingAdmin(), 403);
                    $checked = $record->check();
                    Notification::make()->title(__('advertising::gateway.'.$checked->status))->send();
                }),
            Action::make('invoice')->label(__('advertising::billing.invoice'))->url(fn (OnlinePaymentAttempt $record): string => route('advertising.invoices.show', $record->invoice_id)),
        ])->defaultSort('created_at', 'desc');
    }

    public static function getPages(): array
    {
        return ['index' => ListOnlinePaymentAttempts::route('/')];
    }
}
