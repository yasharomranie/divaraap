<?php

declare(strict_types=1);

namespace Modules\Category\Support;

final class TradeChecklistCatalog
{
    public static function group(string $slug): ?string
    {
        return match ($slug) {
            'electronics-phones' => 'phones',
            'electronics' => 'electronics',
            'vehicles' => 'vehicles',
            'real-estate' => 'property',
            'fashion' => 'fashion',
            'home-garden' => 'home',
            'sports' => 'sports',
            'jobs' => 'jobs',
            'services' => 'services',
            default => null,
        };
    }

    public static function rows(string $group): array
    {
        $rows = [];
        foreach (['general', $group] as $section) {
            if ($section === 'general' && $rows !== []) {
                continue;
            }
            $fa = trans('category::checklists.templates.'.$section, [], 'fa');
            $en = trans('category::checklists.templates.'.$section, [], 'en');
            if (is_array($fa) && is_array($en)) {
                foreach ($fa as $key => $label) {
                    $rows[$section.'.'.$key] = ['fa' => $label, 'en' => $en[$key] ?? $label];
                }
            }
        }

        return array_values($rows);
    }
}
