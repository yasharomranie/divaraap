<?php

declare(strict_types=1);

namespace Modules\Advertising\Listeners;

use Modules\Advertising\Models\AdvertisingEvent;
use Modules\User\App\Events\ShopViewed;

final class RecordShopVisit
{
    public function handle(ShopViewed $event): void
    {
        try {
            AdvertisingEvent::recordPostClick('shop_view', $event->shopId);
        } catch (\Throwable $exception) {
            report($exception);
        }
    }
}
