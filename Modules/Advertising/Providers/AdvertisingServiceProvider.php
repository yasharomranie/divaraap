<?php

declare(strict_types=1);

namespace Modules\Advertising\Providers;

use Illuminate\Support\ServiceProvider;
use Modules\Advertising\Support\BannerRenderer;

final class AdvertisingServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->scoped(BannerRenderer::class);
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(module_path('Advertising', 'Database/migrations'));
        $this->loadViewsFrom(module_path('Advertising', 'resources/views'), 'advertising');
        $this->loadTranslationsFrom(module_path('Advertising', 'lang'), 'advertising');
    }
}
