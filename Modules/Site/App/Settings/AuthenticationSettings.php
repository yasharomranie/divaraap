<?php

declare(strict_types=1);

namespace Modules\Site\App\Settings;

use Spatie\LaravelSettings\Settings;

final class AuthenticationSettings extends Settings
{
    public array $login_methods;

    public array $registration_methods;

    public string $smsir_api_key;

    public int $smsir_template_id;

    public string $smsir_parameter;

    public int $sms_daily_limit;

    public static function group(): string
    {
        return 'authentication';
    }

    public static function encrypted(): array
    {
        return ['smsir_api_key'];
    }
}
