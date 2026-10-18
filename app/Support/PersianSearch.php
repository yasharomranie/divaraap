<?php

declare(strict_types=1);

namespace App\Support;

final class PersianSearch
{
    public static function replacements(): array
    {
        return [
            ...array_combine(preg_split('//u', '۰۱۲۳۴۵۶۷۸۹٠١٢٣٤٥٦٧٨٩', -1, PREG_SPLIT_NO_EMPTY), str_split('01234567890123456789')),
            'ي' => 'ی', 'ى' => 'ی', 'ك' => 'ک', 'ڪ' => 'ک',
            'أ' => 'ا', 'إ' => 'ا', 'ٱ' => 'ا', 'آ' => 'ا',
            'َ' => '', 'ِ' => '', 'ُ' => '', 'ً' => '', 'ٍ' => '', 'ٌ' => '', 'ْ' => '', 'ّ' => '',
            'ـ' => '', "\u{200c}" => '', "\u{200d}" => '',
            ' ' => '', "\t" => '', "\n" => '', "\r" => '', "\u{00a0}" => '', "\u{202f}" => '',
        ];
    }

    public static function terms(string $search): ?array
    {
        if (mb_strlen($search) > 120) {
            return null;
        }
        $parts = preg_split('/[\s\x{00a0}\x{202f}]+/u', trim($search), -1, PREG_SPLIT_NO_EMPTY);
        if ($parts === false) {
            return null;
        }
        if ($parts === []) {
            return [];
        }
        $terms = array_values(array_unique(array_filter(array_map(fn (string $part) => mb_strtolower(strtr($part, self::replacements())), $parts), fn (string $term) => $term !== '')));

        return $terms !== [] && count($terms) <= 8 ? $terms : null;
    }

    public static function sql(string $column): string
    {
        $expression = "LOWER(COALESCE({$column}, ''))";
        foreach (self::replacements() as $from => $to) {
            $expression = "REPLACE({$expression}, '".str_replace("'", "''", (string) $from)."', '".str_replace("'", "''", $to)."')";
        }

        return $expression;
    }

    public static function pattern(string $term): string
    {
        return '%'.strtr($term, ['!' => '!!', '%' => '!%', '_' => '!_']).'%';
    }
}
