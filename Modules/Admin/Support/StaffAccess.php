<?php

declare(strict_types=1);

namespace Modules\Admin\Support;

use Modules\Advertising\Models\Banner;
use Modules\Advertising\Models\BannerPlacement;

final class StaffAccess
{
    public const ROLES = [
        'advertising-manager' => ['admin-panel.access', 'operations.view', 'advertising.manage'],
        'moderator' => ['admin-panel.access', 'operations.view', 'moderation.listings', 'moderation.reviews', 'moderation.reports', 'moderation.advertising'],
        'support' => ['admin-panel.access', 'operations.view', 'moderation.reports'],
    ];

    public static function permissionFor(string $model): ?string
    {
        return match ($model) {
            Banner::class,
            BannerPlacement::class => 'advertising.manage',
            default => null,
        };
    }
}
