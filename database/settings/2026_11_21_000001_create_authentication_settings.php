<?php

declare(strict_types=1);

use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    public function up(): void
    {
        foreach (['login_methods', 'registration_methods'] as $name) {
            $this->migrator->add('authentication.'.$name, ['email', 'google', 'apple', 'facebook']);
        }
        $this->migrator->addEncrypted('authentication.smsir_api_key', '');
        $this->migrator->add('authentication.smsir_template_id', 0);
        $this->migrator->add('authentication.smsir_parameter', 'CODE');
        $this->migrator->add('authentication.sms_daily_limit', 200);
    }

    public function down(): void
    {
        foreach (['login_methods', 'registration_methods', 'smsir_api_key', 'smsir_template_id', 'smsir_parameter', 'sms_daily_limit'] as $name) {
            $this->migrator->delete('authentication.'.$name);
        }
    }
};
