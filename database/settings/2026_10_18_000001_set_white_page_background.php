<?php

declare(strict_types=1);

use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    public function up(): void
    {
        $this->migrator->update('appearance.colors', fn ($colors): array => array_replace((array) $colors, ['page' => '#ffffff']));
    }

    public function down(): void
    {
        $this->migrator->update('appearance.colors', fn ($colors): array => array_replace((array) $colors, ['page' => '#f5f5f7']));
    }
};
