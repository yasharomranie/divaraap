<?php

declare(strict_types=1);

namespace Modules\Support\Filament\Admin\Resources;

use BackedEnum;
use Filament\Actions\ViewAction;
use Filament\Resources\Resource;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Modules\Support\Filament\Admin\Resources\SupportTicketResource\Pages\ListSupportTickets;
use Modules\Support\Filament\Admin\Resources\SupportTicketResource\Pages\ViewSupportTicket;
use Modules\Support\Models\SupportTicket;
use Modules\User\App\Models\User;
use UnitEnum;

final class SupportTicketResource extends Resource
{
    protected static ?string $model = SupportTicket::class;

    protected static ?string $slug = 'support-tickets';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-lifebuoy';

    public static function canViewAny(): bool
    {
        return SupportTicket::staff(auth()->user());
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

    public static function canView(Model $record): bool
    {
        return self::canViewAny() && self::getEloquentQuery()->whereKey($record->id)->exists();
    }

    public static function getEloquentQuery(): Builder
    {
        return SupportTicket::staffQuery();
    }

    public static function getModelLabel(): string
    {
        return __('support::messages.ticket');
    }

    public static function getPluralModelLabel(): string
    {
        return __('support::messages.title');
    }

    public static function getNavigationGroup(): string|UnitEnum|null
    {
        return __('support::messages.group');
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('reference')->label(__('support::messages.reference'))->searchable(),
            TextColumn::make('subject')->label(__('support::messages.subject'))->limit(45)->wrap(),
            TextColumn::make('state')->label(__('support::messages.state'))->badge()->formatStateUsing(fn (string $state) => __('support::messages.state_'.$state)),
            TextColumn::make('category')->label(__('support::messages.category'))->formatStateUsing(fn (string $state) => __('support::messages.category_'.$state)),
            TextColumn::make('user_id')->label(__('support::messages.owner'))->formatStateUsing(fn ($state) => User::find($state)?->name ?? '#'.$state),
            TextColumn::make('assignee_id')->label(__('support::messages.assignee'))->formatStateUsing(fn ($state) => User::find($state)?->name ?? '#'.$state)->placeholder(__('support::messages.unassigned')),
            TextColumn::make('last_activity_at')->label(__('support::messages.updated_at'))->dateTime()->timezone('Asia/Tehran'),
        ])->defaultSort('last_activity_at', 'desc')->filters([
            SelectFilter::make('state')->label(__('support::messages.state'))->options(collect(SupportTicket::STATES)->mapWithKeys(fn ($state) => [$state => __('support::messages.state_'.$state)])->all()),
        ])->recordActions([ViewAction::make()->label(__('support::messages.view'))]);
    }

    public static function getPages(): array
    {
        return ['index' => ListSupportTickets::route('/'), 'view' => ViewSupportTicket::route('/{record}')];
    }
}
