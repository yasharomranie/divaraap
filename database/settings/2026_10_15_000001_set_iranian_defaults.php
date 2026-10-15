<?php

declare(strict_types=1);

use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    public function up(): void
    {
        $this->migrator->update('general.currencies', fn (): array => ['IRR']);
        $this->migrator->update('general.default_country_code', fn (): string => '+98');
    }

    public function down(): void
    {
        $this->migrator->update('general.currencies', fn (): array => ['USD']);
        $this->migrator->update('general.default_country_code', fn (): string => '+90');
    }
};
