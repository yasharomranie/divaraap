<?php

declare(strict_types=1);

return [
    'name' => env('APP_NAME', 'Laravel'),
    'env' => env('APP_ENV', 'production'),
    'debug' => (bool) env('APP_DEBUG', false),
    'url' => env('APP_URL', 'http://localhost'),
    'timezone' => 'UTC',
    'locale' => env('APP_LOCALE', 'fa'),

    'available_locales' => ['fa', 'en', 'tr', 'ar', 'zh', 'es', 'fr', 'de', 'pt', 'ru', 'ja'],

    'locale_names' => [
        'fa' => 'فارسی', 'en' => 'English', 'tr' => 'Türkçe', 'ar' => 'العربية',
        'zh' => '中文', 'es' => 'Español', 'fr' => 'Français', 'de' => 'Deutsch',
        'pt' => 'Português', 'ru' => 'Русский', 'ja' => '日本語',
    ],

    'rtl_locales' => ['fa', 'ar'],

    'currencies' => ['USD'],

    'fallback_locale' => env('APP_FALLBACK_LOCALE', 'en'),

    'faker_locale' => env('APP_FAKER_LOCALE', 'en_US'),
    'cipher' => 'AES-256-CBC',

    'key' => env('APP_KEY'),

    'previous_keys' => [
        ...array_filter(
            explode(',', (string) env('APP_PREVIOUS_KEYS', ''))
        ),
    ],
    'maintenance' => [
        'driver' => env('APP_MAINTENANCE_DRIVER', 'file'),
        'store' => env('APP_MAINTENANCE_STORE', 'database'),
    ],
];
