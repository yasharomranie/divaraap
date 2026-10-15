<?php

declare(strict_types=1);

namespace Modules\Listing\Support;

use App\Support\Money;
use Modules\Site\App\Settings\GeneralSettings;
use Throwable;

class ListingPanelHelper
{
    public static function currencyCodes(): array
    {
        return [(string) config('app.default_currency', 'IRR')];
    }

    public static function currencyOptions(?string $legacyCurrency = null): array
    {
        return collect(array_unique([...self::currencyCodes(), ...($legacyCurrency ? [$legacyCurrency] : [])]))
            ->mapWithKeys(fn (string $code) => [$code => Money::currencyLabel($code)])
            ->all();
    }

    public static function defaultCurrency(): string
    {
        return self::currencyCodes()[0] ?? 'IRR';
    }

    public static function normalizeCurrency(?string $currency): string
    {
        $normalized = strtoupper(substr(trim((string) $currency), 0, 3));
        if (preg_match('/^[A-Z]{3}$/', $normalized) === 1) {
            return $normalized;
        }

        return self::defaultCurrency();
    }

    public static function googleMapsEnabled(): bool
    {
        try {
            return (bool) app(GeneralSettings::class)->enable_google_maps;
        } catch (Throwable) {
            return false;
        }
    }
}
