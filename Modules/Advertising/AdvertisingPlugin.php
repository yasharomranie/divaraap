<?php

declare(strict_types=1);

namespace Modules\Advertising;

use Filament\Contracts\Plugin;
use Filament\Panel;
use Nwidart\Modules\Facades\Module;

final class AdvertisingPlugin implements Plugin
{
    public function getId(): string
    {
        return 'advertising';
    }

    public static function make(): static
    {
        return app(self::class);
    }

    public function register(Panel $panel): void
    {
        if (! Module::isEnabled('Advertising')) {
            return;
        }

        $panel->discoverPages(
            in: module_path('Advertising', 'Filament/Admin/Pages'),
            for: 'Modules\\Advertising\\Filament\\Admin\\Pages',
        );
        $panel->discoverResources(
            in: module_path('Advertising', 'Filament/Admin/Resources'),
            for: 'Modules\\Advertising\\Filament\\Admin\\Resources',
        );
    }

    public function boot(Panel $panel): void {}
}
