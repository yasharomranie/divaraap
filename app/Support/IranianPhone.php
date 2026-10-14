<?php

declare(strict_types=1);

namespace App\Support;

final class IranianPhone
{
    public static function normalize(string $value): string
    {
        $value = trim(PersianLocale::normalize($value));
        $compact = preg_replace('/[\s()\-]+/u', '', $value) ?? $value;

        if (preg_match('/^(?:09[0-9]{9}|(?:\+98|0098)9[0-9]{9})$/', $compact) !== 1) {
            return $value;
        }

        return '+98'.substr($compact, -10);
    }

    public static function isIranian(string $value): bool
    {
        return preg_match('/^(?:09|\+98|0098)/', trim(PersianLocale::normalize($value))) === 1;
    }

    public static function valid(string $value): bool
    {
        return preg_match('/^\+989[0-9]{9}$/', self::normalize($value)) === 1;
    }
}
