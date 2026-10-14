<?php

declare(strict_types=1);

namespace App\Support\Filament;

use Filament\Notifications\NotificationMailbox;

final class FileManagerNotificationMailbox extends NotificationMailbox
{
    public function push(array $notification): void
    {
        if (FileManagerText::active()) {
            foreach (['title', 'body'] as $field) {
                if (is_string($notification[$field] ?? null)) {
                    $notification[$field] = FileManagerText::translate($notification[$field]);
                }
            }
        }
        parent::push($notification);
    }
}
