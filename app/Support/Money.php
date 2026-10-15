<?php

declare(strict_types=1);

namespace App\Support;

use InvalidArgumentException;

final class Money
{
    public static function input(string $value): string
    {
        $value = trim(PersianLocale::normalize($value));

        if (preg_match('/^[0-9]{1,3}(?:[,٬][0-9]{3})+(?:\.[0-9]{1,2})?$/u', $value) === 1) {
            return str_replace([',', '٬'], '', $value);
        }

        return $value;
    }

    public static function decimal(string|int|float $value): string
    {
        $value = self::input((string) $value);

        if (preg_match('/^([0-9]+)(?:\.([0-9]{1,2}))?$/', $value, $parts) !== 1) {
            throw new InvalidArgumentException('Invalid monetary amount.');
        }

        $integer = ltrim($parts[1], '0') ?: '0';

        if (strlen($integer) > 18) {
            throw new InvalidArgumentException('Monetary amount exceeds storage capacity.');
        }

        return $integer.'.'.str_pad($parts[2] ?? '', 2, '0');
    }

    public static function valid(mixed $value, string $currency): bool
    {
        if (! is_string($value) && ! is_int($value) && ! is_float($value)) {
            return false;
        }

        try {
            $decimal = self::decimal($value);

            return strtoupper($currency) !== 'IRR' || str_ends_with($decimal, '.00');
        } catch (InvalidArgumentException) {
            return false;
        }
    }

    public static function currencyLabel(?string $currency): string
    {
        $currency = strtoupper(trim((string) $currency)) ?: 'IRR';

        return $currency === 'IRR' ? (app()->getLocale() === 'fa' ? 'ریال' : 'IRR') : $currency;
    }

    public static function format(string|int|float $value, ?string $currency): string
    {
        [$integer, $fraction] = explode('.', self::decimal($value));
        $separator = app()->getLocale() === 'fa' ? '٬' : ',';
        $decimalSeparator = app()->getLocale() === 'fa' ? '٫' : '.';
        $integer = preg_replace('/\B(?=(?:[0-9]{3})+(?![0-9]))/', $separator, $integer) ?? $integer;
        $number = $integer.((strtoupper((string) ($currency ?: 'IRR')) === 'IRR' && $fraction === '00') ? '' : $decimalSeparator.$fraction);

        return PersianLocale::digits($number).' '.self::currencyLabel($currency);
    }
}
