<?php

declare(strict_types=1);

namespace Modules\User\Filament\Admin\Resources;

use App\Support\Filament\ModelStateSelectColumn;
use App\Support\PersianLocale;
use BackedEnum;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Modules\Admin\Support\Filament\AccessControlledResource as Resource;
use Modules\Admin\Support\Filament\ResourceTableActions;
use Modules\Admin\Support\Filament\ResourceTableColumns;
use Modules\User\App\Models\User;
use Modules\User\App\States\UserStatus;
use Modules\User\App\Support\Filament\UserFormFields;
use Modules\User\Filament\Admin\Resources\UserResource\Pages;
use STS\FilamentImpersonate\Actions\Impersonate;
use UnitEnum;

class UserResource extends Resource
{
    protected static ?string $model = User::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-users';

    protected static string|UnitEnum|null $navigationGroup = 'User Management';

    public static function getModelLabel(): string
    {
        return __('user::admin.user');
    }

    public static function getPluralModelLabel(): string
    {
        return __('user::admin.users');
    }

    public static function getNavigationLabel(): string
    {
        return __('user::admin.users');
    }

    public static function getNavigationGroup(): string|UnitEnum|null
    {
        return __('user::admin.user_management');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->schema([
            UserFormFields::name(),
            UserFormFields::email(),
            UserFormFields::password(fn ($livewire) => $livewire instanceof Pages\CreateUser),
            UserFormFields::status(),
            UserFormFields::roles(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            ResourceTableColumns::id(),
            TextColumn::make('name')->label(__('user::admin.name'))->searchable()->sortable(),
            TextColumn::make('email')->label(__('user::admin.email'))->searchable()->sortable(),
            TextColumn::make('roles.name')->label(__('user::admin.roles'))->formatStateUsing(fn (string $state): string => User::roleLabel($state))->badge()->label(__('user::admin.roles')),
            ModelStateSelectColumn::make('status')->label(__('user::admin.status'))
                ->options(UserStatus::options())
                ->disabled(fn (User $record): bool => ! static::canEdit($record)),
            TextColumn::make('created_at')->label(__('user::admin.created_at'))->formatStateUsing(fn ($state): string => PersianLocale::date($state, true))->sortable(),
        ])->defaultSort('id', 'desc')->filters([
            SelectFilter::make('status')->label(__('user::admin.status'))->options(UserStatus::options()),
        ])->actions(ResourceTableActions::editActivityDelete(static::class, [
            Impersonate::make(),
        ]));
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListUsers::route('/'),
            'create' => Pages\CreateUser::route('/create'),
            'activities' => Pages\ListUserActivities::route('/{record}/activities'),
            'edit' => Pages\EditUser::route('/{record}/edit'),
        ];
    }
}
