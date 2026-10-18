<?php

declare(strict_types=1);

use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    public function up(): void
    {
        $this->migrator->add('operations.max_ads_per_page', 10);
        $this->migrator->add('operations.min_listing_gap', 1);
        $this->migrator->add('operations.telemetry_enabled', true);
    }

    public function down(): void
    {
        foreach (['max_ads_per_page', 'min_listing_gap', 'telemetry_enabled'] as $key) {
            $this->migrator->delete('operations.'.$key);
        }
    }
};
