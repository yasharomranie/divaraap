<?php

declare(strict_types=1);

namespace Modules\Site\App\Settings;

use Spatie\LaravelSettings\Settings;

final class PaymentGatewaySettings extends Settings
{
    public bool $zarinpal_enabled;

    public bool $zarinpal_sandbox;

    public string $zarinpal_merchant;

    public bool $zibal_enabled;

    public bool $zibal_sandbox;

    public string $zibal_merchant;

    public static function group(): string
    {
        return 'payment_gateways';
    }

    public static function encrypted(): array
    {
        return ['zarinpal_merchant', 'zibal_merchant'];
    }
}
