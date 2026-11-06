<?php

declare(strict_types=1);

use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    public function up(): void
    {
        $this->migrator->add('email_notifications.enabled', false);
        $this->migrator->add('email_notifications.per_user_daily', 5);
        $this->migrator->add('email_notifications.site_daily', 1000);
    }

    public function down(): void
    {
        foreach (['enabled', 'per_user_daily', 'site_daily'] as $key) {
            $this->migrator->delete('email_notifications.'.$key);
        }
    }
};
