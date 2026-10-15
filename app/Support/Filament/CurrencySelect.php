<?php

declare(strict_types=1);

namespace App\Support\Filament;

use App\Support\Money;
use Filament\Forms\Components\Select;
use Illuminate\Database\Eloquent\Model;

final class CurrencySelect
{
    public static function make(): Select
    {
        return Select::make('currency')
            ->options(fn (?Model $record): array => [$record?->getAttribute('currency') ?: 'IRR' => Money::currencyLabel($record?->getAttribute('currency') ?: 'IRR')])
            ->default('IRR')
            ->required()
            ->disabledOn('edit')
            ->dehydrated();
    }
}
