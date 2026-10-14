<?php

declare(strict_types=1);

namespace Modules\Notification\Filament\Admin\Resources;

use App\Support\PersianLocale;
use BackedEnum;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Modules\Admin\Support\Filament\ResourceTableActions;
use Modules\Admin\Support\Filament\ResourceTableColumns;
use Modules\Notification\Filament\Admin\Resources\UserNotificationResource\Pages;
use Modules\Notification\Models\UserNotification;
use UnitEnum;

class UserNotificationResource extends Resource
{
    protected static ?string $model = UserNotification::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-bell';

    protected static string|UnitEnum|null $navigationGroup = 'Moderation';

    protected static ?string $label = 'Notification';

    protected static ?string $pluralLabel = 'Notifications';

    protected static ?int $navigationSort = 3;

    public static function getModelLabel(): string
    {
        return __('notification::admin.notification');
    }

    public static function getPluralModelLabel(): string
    {
        return __('notification::admin.notifications');
    }

    public static function getNavigationLabel(): string
    {
        return __('notification::admin.notifications');
    }

    public static function getNavigationGroup(): string|UnitEnum|null
    {
        return __('notification::admin.moderation');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->schema([
            TextInput::make('user_id')->label(__('notification::admin.user'))->extraInputAttributes(['dir' => 'auto'])->numeric()->required(),
            TextInput::make('type')->label(__('notification::admin.type'))->extraInputAttributes(['dir' => 'auto'])->maxLength(48)->required(),
            TextInput::make('title')->label(__('notification::admin.title'))->extraInputAttributes(['dir' => 'auto'])->maxLength(150)->required(),
            Textarea::make('body')->label(__('notification::admin.body'))->rows(3),
            TextInput::make('action_url')->label(__('notification::admin.action_url'))->extraInputAttributes(['dir' => 'ltr'])->maxLength(255),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            ResourceTableColumns::id(),
            TextColumn::make('title')->label(__('notification::admin.title'))->searchable()->limit(48),
            TextColumn::make('type')->label(__('notification::admin.type'))->formatStateUsing(fn (string $state): string => __('notification::admin.'.$state))->badge()->sortable(),
            TextColumn::make('user_id')->label(__('notification::admin.user'))->sortable(),
            TextColumn::make('read_at')->label(__('notification::admin.read_at'))->formatStateUsing(fn ($state): string => PersianLocale::date($state, true))->label(__('notification::admin.read'))->placeholder(__('notification::admin.unread')),
            ResourceTableColumns::createdAtHidden(),
        ])->defaultSort('id', 'desc')->filters([
            SelectFilter::make('type')->label(__('notification::admin.type'))->options([
                'offer' => __('notification::admin.offer'), 'message' => __('notification::admin.message'), 'review' => __('notification::admin.review'),
                'listing' => __('notification::admin.listing'), 'promotion' => __('notification::admin.promotion'), 'saved_search' => __('notification::admin.saved_search'),
            ]),
        ])->actions(ResourceTableActions::editDelete());
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListUserNotificationResourceRecords::route('/'),
            'create' => Pages\CreateUserNotificationResourceRecord::route('/create'),
            'edit' => Pages\EditUserNotificationResourceRecord::route('/{record}/edit'),
        ];
    }
}
