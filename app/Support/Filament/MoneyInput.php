<?php

declare(strict_types=1);

namespace App\Support\Filament;

use App\Rules\MonetaryAmount;
use App\Support\Money;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Utilities\Get;

final class MoneyInput
{
    public static function make(string $name): TextInput
    {
        return TextInput::make($name)
            ->extraInputAttributes(['dir' => 'ltr', 'inputmode' => 'decimal'])
            ->mutateStateForValidationUsing(fn ($state) => is_string($state) ? Money::input($state) : $state)
            ->rules(fn (Get $get): array => [new MonetaryAmount((string) ($get('currency') ?: 'IRR'))])
            ->dehydrateStateUsing(fn ($state) => blank($state) ? null : Money::decimal($state))
            ->suffix(fn (Get $get): string => Money::currencyLabel($get('currency') ?: 'IRR'));
    }
}
