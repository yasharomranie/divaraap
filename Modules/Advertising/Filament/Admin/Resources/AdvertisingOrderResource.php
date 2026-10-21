<?php

declare(strict_types=1);

namespace Modules\Advertising\Filament\Admin\Resources;

use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Modules\Advertising\Filament\Admin\Resources\AdvertisingOrderResource\Pages;
use Modules\Advertising\Models\AdvertisingInvoice;
use Modules\Advertising\Models\AdvertisingOrder;
use Modules\Advertising\Models\Campaign;

final class AdvertisingOrderResource extends AdvertisingResource
{
    protected static ?string $model = AdvertisingOrder::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-clipboard-document-list';

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
        return __('advertising::orders.order');
    }

    public static function getPluralModelLabel(): string
    {
        return __('advertising::orders.orders');
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('id')->label(__('advertising::orders.number'))->sortable(),
            TextColumn::make('title')->label(__('advertising::orders.title'))->searchable(),
            TextColumn::make('state')->label(__('advertising::billing.state'))->formatStateUsing(fn ($state) => __('advertising::orders.'.$state))->badge(),
            TextColumn::make('placement')->label(__('advertising::orders.placement'))->formatStateUsing(fn ($state) => __('advertising::admin.'.$state)),
            TextColumn::make('duration_days')->label(__('advertising::orders.duration'))->numeric(),
            TextColumn::make('requested_budget')->label(__('advertising::orders.budget'))->numeric(),
            TextColumn::make('created_at')->label(__('advertising::orders.created'))->dateTime()->timezone('Asia/Tehran'),
        ])->actions([
            Action::make('details')->label(__('advertising::orders.details'))->url(fn (AdvertisingOrder $record): string => route('advertising.orders.show', $record)),
            Action::make('quote')->label(__('advertising::orders.quote'))->visible(fn (AdvertisingOrder $record): bool => $record->state === 'submitted')
                ->requiresConfirmation()->modalDescription(__('advertising::orders.quote_warning'))
                ->schema([
                    TextInput::make('amount')->label(__('advertising::billing.amount'))->integer()->minValue(1)->maxValue(1000000000000)->required(),
                    Textarea::make('terms')->label(__('advertising::orders.terms'))->required()->maxLength(1000),
                    Select::make('campaign_id')->label(__('advertising::campaign.campaign'))->options(fn (): array => Campaign::options())->searchable(),
                ])->action(fn (AdvertisingOrder $record, array $data) => $record->quote((string) $data['amount'], $data['terms'], filled($data['campaign_id'] ?? null) ? (int) $data['campaign_id'] : null)),
            Action::make('reject')->label(__('advertising::orders.reject'))->color('danger')->requiresConfirmation()
                ->visible(fn (AdvertisingOrder $record): bool => in_array($record->state, ['submitted', 'quoted', 'accepted'], true))
                ->schema([Textarea::make('reason')->label(__('advertising::billing.reason'))->required()->maxLength(500)])
                ->action(fn (AdvertisingOrder $record, array $data) => $record->reject($data['reason'])),
            Action::make('complete')->label(__('advertising::orders.complete'))->requiresConfirmation()->modalDescription(__('advertising::orders.complete_hint'))
                ->visible(fn (AdvertisingOrder $record): bool => $record->state === 'accepted')
                ->schema([Select::make('campaign_id')->label(__('advertising::campaign.campaign'))->options(fn (): array => Campaign::options())->searchable()->required()])
                ->action(fn (AdvertisingOrder $record, array $data) => $record->complete((int) $data['campaign_id'])),
        ])->defaultSort('id', 'desc');
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ListAdvertisingOrders::route('/')];
    }
}
