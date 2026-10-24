<?php

declare(strict_types=1);

namespace Modules\Advertising\Listeners;

use Modules\Advertising\Models\AdvertisingEvent;
use Modules\Conversation\App\Events\ServiceRequestSubmitted;

final class RecordServiceRequest
{
    public function handle(ServiceRequestSubmitted $event): void
    {
        try {
            AdvertisingEvent::recordPostClick('service', $event->listingId);
        } catch (\Throwable $exception) {
            report($exception);
        }
    }
}
