<?php

declare(strict_types=1);

namespace Modules\Site\App\Settings;

use Spatie\LaravelSettings\Settings;

final class DeploymentSettings extends Settings
{
    public string $repository;

    public string $ref;

    public string $github_token;

    public static function group(): string
    {
        return 'deployment';
    }

    public static function encrypted(): array
    {
        return ['github_token'];
    }
}
