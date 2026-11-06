<?php

declare(strict_types=1);

namespace Modules\Support;

use Filament\Contracts\Plugin;
use Filament\Panel;

final class SupportPlugin implements Plugin
{
    public function getId(): string
    {
        return 'support';
    }

    public static function make(): static
    {
        return new self;
    }

    public function register(Panel $panel): void
    {
        $panel->discoverResources(in: module_path('Support', 'Filament/Admin/Resources'), for: 'Modules\\Support\\Filament\\Admin\\Resources');
    }

    public function boot(Panel $panel): void {}
}
