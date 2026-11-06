<?php

declare(strict_types=1);

namespace Modules\Notification\Filament\Admin\Resources;

use BackedEnum;
use Filament\Resources\Resource;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Modules\Notification\Filament\Admin\Resources\EmailDeliveryResource\Pages\ListEmailDeliveries;
use Modules\Notification\Models\EmailDelivery;
use UnitEnum;

final class EmailDeliveryResource extends Resource
{
    protected static ?string $model = EmailDelivery::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-paper-airplane';

    public static function canViewAny(): bool
    {
        return auth()->user()?->hasRole('admin') && (string) auth()->user()->status === 'active';
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
        return __('notification::email.deliveries');
    }

    public static function getPluralModelLabel(): string
    {
        return self::getModelLabel();
    }

    public static function getNavigationGroup(): string|UnitEnum|null
    {
        return __('notification::admin.moderation');
    }

    public static function table(Table $table): Table
    {
        $states = ['pending', 'processing', 'accepted', 'skipped', 'uncertain', 'failed'];

        return $table->columns([
            TextColumn::make('id')->label(__('notification::email.delivery_id'))->numeric(),
            TextColumn::make('notification_id')->label(__('notification::admin.notification'))->numeric(),
            TextColumn::make('state')->label(__('notification::email.state'))->badge()->formatStateUsing(fn (string $state): string => __('notification::email.state_'.$state)),
            TextColumn::make('outcome')->label(__('notification::email.outcome'))->wrap()->formatStateUsing(fn (?string $state): string => $state ? __('notification::email.outcome_'.$state) : '—'),
            TextColumn::make('created_at')->label(__('notification::email.created_at'))->dateTime()->timezone('Asia/Tehran'),
            TextColumn::make('accepted_at')->label(__('notification::email.accepted_at'))->dateTime()->timezone('Asia/Tehran'),
        ])->defaultSort('id', 'desc')->filters([SelectFilter::make('state')->label(__('notification::email.state'))->options(collect($states)->mapWithKeys(fn ($state) => [$state => __('notification::email.state_'.$state)])->all())]);
    }

    public static function getPages(): array
    {
        return ['index' => ListEmailDeliveries::route('/')];
    }
}
