<?php

declare(strict_types=1);

namespace Modules\Conversation\App\Support;

class QuickMessageCatalog
{
    public static function all(): array
    {
        return [
            __('conversation::messages.quick_hi'),
            __('conversation::messages.quick_available'),
            __('conversation::messages.quick_price'),
            __('conversation::messages.quick_thanks'),
        ];
    }
}
