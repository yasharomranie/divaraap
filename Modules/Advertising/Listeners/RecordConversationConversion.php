<?php

declare(strict_types=1);

namespace Modules\Advertising\Listeners;

use Modules\Advertising\Models\AdvertisingEvent;
use Modules\Conversation\App\Events\ConversationStarted;

final class RecordConversationConversion
{
    public function handle(ConversationStarted $event): void
    {
        try {
            AdvertisingEvent::recordConversation($event->listingId);
        } catch (\Throwable $exception) {
            report($exception);
        }
    }
}
