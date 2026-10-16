<?php

declare(strict_types=1);

namespace Modules\Site\App\Settings;

use Spatie\LaravelSettings\Settings;

final class AppearanceSettings extends Settings
{
    public array $colors;

    public array $typography;

    public static function group(): string
    {
        return 'appearance';
    }
}
