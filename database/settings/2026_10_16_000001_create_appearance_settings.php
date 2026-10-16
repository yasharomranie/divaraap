<?php

declare(strict_types=1);

use App\Support\AppearanceTheme;
use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    public function up(): void
    {
        foreach (AppearanceTheme::defaults() as $property => $value) {
            $this->migrator->add('appearance.'.$property, $value);
        }
    }

    public function down(): void
    {
        foreach (['colors', 'typography'] as $property) {
            $this->migrator->delete('appearance.'.$property);
        }
    }
};
