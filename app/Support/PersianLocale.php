<?php

declare(strict_types=1);

namespace App\Support;

use Carbon\CarbonImmutable;
use DateTimeInterface;
use IntlDateFormatter;

final class PersianLocale
{
    public static function normalize(string $value): string
    {
        return strtr($value, array_combine(
            preg_split('//u', '۰۱۲۳۴۵۶۷۸۹٠١٢٣٤٥٦٧٨٩٫', -1, PREG_SPLIT_NO_EMPTY),
            str_split('01234567890123456789.'),
        ));
    }

    public static function digits(string|int $value): string
    {
        return app()->getLocale() === 'fa'
            ? strtr((string) $value, array_combine(str_split('0123456789'), preg_split('//u', '۰۱۲۳۴۵۶۷۸۹', -1, PREG_SPLIT_NO_EMPTY)))
            : (string) $value;
    }

    public static function number(float|int|string $value, int $decimals = 0): string
    {
        $formatted = number_format((float) $value, $decimals);

        return self::digits(app()->getLocale() === 'fa' ? strtr($formatted, [',' => '٬', '.' => '٫']) : $formatted);
    }

    public static function date(DateTimeInterface|string|null $value, bool $withTime = false): string
    {
        if ($value === null || $value === '') {
            return '';
        }

        $date = CarbonImmutable::parse($value);

        if (app()->getLocale() !== 'fa') {
            return $date->locale(app()->getLocale())->isoFormat($withTime ? 'LL HH:mm' : 'LL');
        }

        $formatter = new IntlDateFormatter('fa_IR@calendar=persian', IntlDateFormatter::NONE, IntlDateFormatter::NONE, 'Asia/Tehran', IntlDateFormatter::TRADITIONAL, $withTime ? 'd MMMM yyyy، HH:mm' : 'd MMMM yyyy');

        return (string) $formatter->format($date);
    }

    public static function time(DateTimeInterface|string|null $value): string
    {
        if ($value === null || $value === '') {
            return '';
        }

        $date = CarbonImmutable::parse($value);

        return self::digits($date->setTimezone(app()->getLocale() === 'fa' ? 'Asia/Tehran' : $date->getTimezone())->format('H:i'));
    }
}
