<?php

declare(strict_types=1);

use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    public function up(): void
    {
        foreach (['zarinpal', 'zibal'] as $gateway) {
            $this->migrator->add('payment_gateways.'.$gateway.'_enabled', false);
            $this->migrator->add('payment_gateways.'.$gateway.'_sandbox', true);
            $this->migrator->addEncrypted('payment_gateways.'.$gateway.'_merchant', '');
        }
    }

    public function down(): void
    {
        foreach (['zarinpal', 'zibal'] as $gateway) {
            foreach (['enabled', 'sandbox', 'merchant'] as $field) {
                $this->migrator->delete('payment_gateways.'.$gateway.'_'.$field);
            }
        }
    }
};
