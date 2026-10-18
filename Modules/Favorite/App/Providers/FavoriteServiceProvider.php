<?php

declare(strict_types=1);

namespace Modules\Favorite\App\Providers;

use Illuminate\Support\ServiceProvider;
use Modules\Favorite\App\Models\BuyerPublication;
use Modules\Listing\Models\Listing;

class FavoriteServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Listing::saved(BuyerPublication::record(...));
        $this->loadMigrationsFrom(module_path('Favorite', 'Database/migrations'));
        $this->loadRoutesFrom(module_path('Favorite', 'routes/web.php'));
        $this->loadViewsFrom(module_path('Favorite', 'resources/views'), 'favorite');
        $this->loadTranslationsFrom(module_path('Favorite', 'lang'), 'favorite');
    }

    public function register(): void {}
}
