<?php

declare(strict_types=1);

namespace App\Support\Filament;

trait HasAccessControlledPage
{
    abstract protected static function getAccessPermission(): string;

    public static function canAccess(): bool
    {
        return (string) auth()->user()?->status === 'active' && (bool) auth()->user()?->can(static::getAccessPermission());
    }
}
