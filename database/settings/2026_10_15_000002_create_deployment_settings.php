<?php

declare(strict_types=1);

use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    public function up(): void
    {
        $this->migrator->add('deployment.repository', 'yasharomranie/divaraap');
        $this->migrator->add('deployment.ref', 'main');
        $this->migrator->addEncrypted('deployment.github_token', '');
    }

    public function down(): void
    {
        foreach (['repository', 'ref', 'github_token'] as $property) {
            $this->migrator->delete('deployment.'.$property);
        }
    }
};
