<?php

declare(strict_types=1);

namespace Modules\Listing\Support;

use App\Support\Money;

final class ListingImprovementGuide
{
    public static function evaluate(array $attributes, int $photoCount): array
    {
        $title = self::text((string) ($attributes['title'] ?? ''));
        $description = self::text((string) ($attributes['description'] ?? ''));
        $checks = [
            'title' => self::meaningfulLength($title) >= 10 && mb_strlen($title) <= 70,
            'description' => self::meaningfulLength($description) >= 80,
            'price' => Money::valid($attributes['price'] ?? null, (string) ($attributes['currency'] ?? 'IRR')),
            'city' => self::specified((string) ($attributes['country'] ?? '')) && self::specified((string) ($attributes['city'] ?? '')),
            'photos' => $photoCount > 0,
        ];
        $items = [];
        foreach ($checks as $key => $complete) {
            $items[] = ['key' => $key, 'complete' => $complete, 'anchor' => $key === 'photos' ? 'listing-images' : $key];
        }

        return ['items' => $items, 'completed' => count(array_filter($checks)), 'total' => count($checks), 'photo_count' => $photoCount];
    }

    private static function text(string $value): string
    {
        $value = strip_tags(html_entity_decode($value, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        $value = preg_replace('/[\p{Cc}\p{Cf}]/u', '', $value) ?? '';

        return trim(preg_replace('/\s+/u', ' ', $value) ?? '');
    }

    private static function meaningfulLength(string $value): int
    {
        return mb_strlen(preg_replace('/[^\p{L}\p{N}]/u', '', $value) ?? '');
    }

    private static function specified(string $value): bool
    {
        $value = mb_strtolower(self::text($value));

        return $value !== '' && ! in_array($value, ['other', 'all cities', 'all countries', 'سایر', 'همه شهرها', 'همه کشورها', 'نامشخص'], true);
    }
}
