<?php

declare(strict_types=1);

namespace Modules\Advertising\Filament\Admin\Resources;

use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Modules\Advertising\Filament\Admin\Resources\PlacementReservationResource\Pages;
use Modules\Advertising\Models\AdvertisingInvoice;
use Modules\Advertising\Models\PlacementReservation;

final class PlacementReservationResource extends AdvertisingResource
{
    protected static ?string $model = PlacementReservation::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-calendar-days';

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
        return __('advertising::reservations.reservation');
    }

    public static function getPluralModelLabel(): string
    {
        return __('advertising::reservations.reservations');
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with('placement');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->schema([
            Hidden::make('request_key')->default(fn (): string => (string) Str::uuid())->required(),
            TextInput::make('title')->label(__('advertising::reservations.title'))->required()->maxLength(120),
            Select::make('placement_id')->label(__('advertising::admin.placement'))->options(fn (): array => PlacementReservation::placements())->required(),
            Select::make('device')->label(__('advertising::admin.device'))->options(collect(['both', 'mobile', 'desktop'])->mapWithKeys(fn ($device): array => [$device => __('advertising::admin.'.$device)]))->required()->default('both'),
            Select::make('order_id')->label(__('advertising::orders.order'))->options(fn (): array => PlacementReservation::orderOptions())->searchable()->helperText(__('advertising::reservations.order_hint')),
            DateTimePicker::make('starts_at')->label(__('advertising::reservations.starts_at'))->timezone('Asia/Tehran')->seconds(false)->required()->helperText(__('advertising::reservations.time_hint')),
            DateTimePicker::make('ends_at')->label(__('advertising::reservations.ends_at'))->timezone('Asia/Tehran')->seconds(false)->required()->after('starts_at'),
            Textarea::make('note')->label(__('advertising::billing.note'))->maxLength(500),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('title')->label(__('advertising::reservations.title'))->searchable(),
            TextColumn::make('placement.key')->label(__('advertising::admin.placement'))->formatStateUsing(fn ($state) => __('advertising::admin.'.$state)),
            TextColumn::make('device')->label(__('advertising::admin.device'))->formatStateUsing(fn ($state) => __('advertising::admin.'.$state)),
            TextColumn::make('starts_at')->label(__('advertising::reservations.starts_at'))->dateTime()->timezone('Asia/Tehran'),
            TextColumn::make('ends_at')->label(__('advertising::reservations.ends_at'))->dateTime()->timezone('Asia/Tehran'),
            TextColumn::make('status')->label(__('advertising::billing.state'))->state(fn (PlacementReservation $record): string => __('advertising::reservations.'.$record->displayState()))->badge(),
            TextColumn::make('order_id')->label(__('advertising::orders.number'))->placeholder('—'),
            TextColumn::make('cancel_reason')->label(__('advertising::reservations.cancel_reason'))->wrap(),
        ])->actions([
            Action::make('cancel')->label(__('advertising::reservations.cancel'))->color('danger')->requiresConfirmation()->modalDescription(__('advertising::reservations.cancel_hint'))
                ->visible(fn (PlacementReservation $record): bool => $record->state === 'confirmed' && $record->ends_at->isFuture())
                ->schema([Textarea::make('reason')->label(__('advertising::reservations.cancel_reason'))->required()->maxLength(500)])
                ->action(fn (PlacementReservation $record, array $data) => $record->cancel($data['reason'])),
        ])->defaultSort('starts_at');
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ListReservations::route('/'), 'create' => Pages\CreateReservation::route('/create')];
    }
}
