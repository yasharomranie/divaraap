<?php

declare(strict_types=1);

namespace Modules\Favorite\App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Notification\Models\UserNotification;

final class BuyerAlertReceipt extends Model
{
    use SoftDeletes;

    public static function deliver(int $userId, string $signature, string $title, string $body, int $listingId): void
    {
        $inserted = self::query()->insertOrIgnore(['user_id' => $userId, 'signature' => hash('sha256', $signature), 'created_at' => now(), 'updated_at' => now()]);
        if ($inserted) {
            UserNotification::publish($userId, UserNotification::TYPE_SAVED_SEARCH, $title, $body, route('listings.show', $listingId));
        }
    }
}
