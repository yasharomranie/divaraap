<?php

declare(strict_types=1);

namespace Modules\Support\Providers;

use Illuminate\Support\ServiceProvider;

final class SupportServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->loadMigrationsFrom(module_path('Support', 'Database/migrations'));
        $this->loadRoutesFrom(module_path('Support', 'routes/web.php'));
        $this->loadViewsFrom(module_path('Support', 'resources/views'), 'support');
        $this->loadTranslationsFrom(module_path('Support', 'lang'), 'support');
    }
}
