<?php

declare(strict_types=1);

namespace Modules\Listing\Support;

final class ListingTextSuggestions
{
    public static function generate(array $context, int $descriptionLimit = 4000): array
    {
        $title = self::clean((string) ($context['title'] ?? ''));
        $description = self::clean((string) ($context['description'] ?? ''), true);
        $category = self::clean((string) ($context['category'] ?? ''));
        $city = self::clean((string) ($context['city'] ?? ''));
        if (in_array(mb_strtolower($city), ['other', 'all cities', 'سایر', 'همه شهرها', 'نامشخص'], true)) {
            $city = '';
        }
        $titles = [];
        if (preg_match('/[\p{L}\p{N}]{2}/u', $title) === 1) {
            $titles[] = ['key' => 'concise', 'text' => self::shorten($title, 70)];
            foreach (['category' => $category, 'city' => $city] as $key => $value) {
                $budget = 70 - mb_strlen($value) - 3;
                if ($value === '' || $budget < 15 || mb_stripos($title, $value) !== false) {
                    continue;
                }
                $titles[] = ['key' => $key, 'text' => $key === 'category'
                    ? $value.' | '.self::shorten($title, $budget)
                    : self::shorten($title, $budget).' — '.$value];
            }
        }
        $descriptions = [];
        if (preg_match('/[\p{L}\p{N}]{2}/u', $description) === 1) {
            $limit = max(1, min(4000, $descriptionLimit));
            $descriptions[] = ['key' => 'tidy', 'text' => self::shorten($description, $limit)];
            $lines = [];
            foreach (['title' => $title, 'category' => $category, 'city' => $city] as $key => $value) {
                if ($value !== '' && mb_stripos($description, $value) === false) {
                    $lines[] = __('listing::suggestions.context_'.$key).': '.self::shorten($value, 100);
                }
            }
            $intro = implode("\n", $lines);
            if ($intro !== '' && mb_strlen($intro) + mb_strlen($description) + 2 <= $limit) {
                $descriptions[] = ['key' => 'structured', 'text' => $intro."\n\n".$description];
            }
        }

        return ['titles' => self::unique($titles), 'descriptions' => self::unique($descriptions)];
    }

    private static function unique(array $items): array
    {
        $seen = [];

        return array_values(array_filter($items, function (array $item) use (&$seen): bool {
            if ($item['text'] === '' || isset($seen[$item['text']])) {
                return false;
            }
            $seen[$item['text']] = true;

            return true;
        }));
    }

    private static function clean(string $value, bool $paragraphs = false): string
    {
        $value = html_entity_decode($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        if ($paragraphs) {
            $value = preg_replace('~<(?:br\s*/?|/p|/div)>~iu', "\n", $value) ?? '';
        }
        $value = strip_tags($value);
        $value = preg_replace('/[\x{0000}-\x{0008}\x{000b}\x{000c}\x{000e}-\x{001f}\x{007f}\x{202a}-\x{202e}\x{2066}-\x{2069}]/u', '', $value) ?? '';
        $value = $paragraphs ? preg_replace('/[^\S\n]+/u', ' ', str_replace("\r", "\n", $value)) : preg_replace('/\s+/u', ' ', $value);

        return trim(preg_replace('/\n{3,}/u', "\n\n", $value ?? '') ?? '');
    }

    private static function shorten(string $value, int $limit): string
    {
        if (mb_strlen($value) <= $limit) {
            return $value;
        }
        $short = mb_substr($value, 0, $limit);
        $space = mb_strrpos($short, ' ');

        return rtrim($space !== false && $space >= $limit * 0.65 ? mb_substr($short, 0, $space) : $short);
    }
}
