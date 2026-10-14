<?php

declare(strict_types=1);

namespace Modules\User\App\Support\Filament;

use App\Support\Filament\ModelStateSelect;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Modules\User\App\States\UserStatus;

class UserFormFields
{
    public static function name(): TextInput
    {
        return TextInput::make('name')->required()->maxLength(255);
    }

    public static function email(): TextInput
    {
        return TextInput::make('email')->email()->required()->maxLength(255)->unique(ignoreRecord: true);
    }

    public static function password(callable $requiredCallback): TextInput
    {
        return TextInput::make('password')
            ->password()
            ->required($requiredCallback)
            ->dehydrateStateUsing(fn ($state) => filled($state) ? bcrypt($state) : null)
            ->dehydrated(fn ($state) => filled($state));
    }

    public static function status(): Select
    {
        return ModelStateSelect::make('status')->options(UserStatus::options())->required();
    }

    public static function roles(): Select
    {
        return Select::make('roles')->multiple()->relationship('roles', 'name')->preload();
    }
}
