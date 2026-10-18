<?php

declare(strict_types=1);

namespace Modules\Admin\Filament\Pages;

use Filament\Pages\Dashboard;
use Filament\Panel;

final class AdminDashboard extends Dashboard
{
    public static function getSlug(?Panel $panel = null): string
    {
        return 'dashboard';
    }

    public static function canAccess(): bool
    {
        return (bool) auth()->user()?->hasRole('admin');
    }
}
