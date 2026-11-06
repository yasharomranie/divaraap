<?php

declare(strict_types=1);

namespace Modules\Site\App\Settings;

use Spatie\LaravelSettings\Settings;

final class EmailNotificationSettings extends Settings
{
    public bool $enabled;

    public int $per_user_daily;

    public int $site_daily;

    public static function group(): string
    {
        return 'email_notifications';
    }
}
