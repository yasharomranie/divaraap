<?php

declare(strict_types=1);

namespace Modules\Advertising\Providers;

use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use Modules\Advertising\Listeners\RecordConversationConversion;
use Modules\Advertising\Listeners\RecordServiceRequest;
use Modules\Advertising\Listeners\RecordShopVisit;
use Modules\Advertising\Support\BannerRenderer;
use Modules\Conversation\App\Events\ConversationStarted;
use Modules\Conversation\App\Events\ServiceRequestSubmitted;
use Modules\User\App\Events\ShopViewed;

final class AdvertisingServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->scoped(BannerRenderer::class);
    }

    public function boot(): void
    {
        Event::listen(ConversationStarted::class, RecordConversationConversion::class);
        Event::listen(ShopViewed::class, RecordShopVisit::class);
        Event::listen(ServiceRequestSubmitted::class, RecordServiceRequest::class);
        $this->loadRoutesFrom(module_path('Advertising', 'routes/web.php'));
        $this->loadMigrationsFrom(module_path('Advertising', 'Database/migrations'));
        $this->loadViewsFrom(module_path('Advertising', 'resources/views'), 'advertising');
        $this->loadTranslationsFrom(module_path('Advertising', 'lang'), 'advertising');
    }
}
