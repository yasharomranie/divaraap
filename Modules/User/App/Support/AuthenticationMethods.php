<?php

declare(strict_types=1);

namespace Modules\User\App\Support;

use Modules\Site\App\Settings\AuthenticationSettings;
use Modules\User\App\Models\User;

final class AuthenticationMethods
{
    public const METHODS = ['email', 'sms', 'google', 'apple', 'facebook'];

    public function allows(string $method, string $context = 'login'): bool
    {
        $settings = app(AuthenticationSettings::class);
        $selected = $context === 'register' ? $settings->registration_methods : $settings->login_methods;

        return in_array($method, $selected, true) && ($method !== 'sms' || $this->smsConfigured());
    }

    public function smsConfigured(): bool
    {
        $settings = app(AuthenticationSettings::class);

        return filled($settings->smsir_api_key) && $settings->smsir_template_id > 0
            && preg_match('/\A[A-Za-z][A-Za-z0-9_]{0,49}\z/', $settings->smsir_parameter) === 1;
    }

    public function canUseSms(User $user): bool
    {
        return (string) $user->status === 'active'
            && ! $user->hasRole(['admin', 'moderator', 'support', 'advertising-manager'])
            && ! $user->can('admin-panel.access')
            && ! $user->breezySessions()->whereNotNull('two_factor_secret')->exists();
    }

    public function fingerprint(): string
    {
        $settings = app(AuthenticationSettings::class);

        return hash_hmac('sha256', json_encode([$settings->smsir_api_key, $settings->smsir_template_id, $settings->smsir_parameter], JSON_THROW_ON_ERROR), config('app.key'));
    }
}
