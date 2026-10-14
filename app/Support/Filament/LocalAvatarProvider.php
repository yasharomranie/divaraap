<?php

declare(strict_types=1);

namespace App\Support\Filament;

use App\Support\UserDirectory;
use Filament\AvatarProviders\Contracts\AvatarProvider;
use Filament\Facades\Filament;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;

final class LocalAvatarProvider implements AvatarProvider
{
    public function get(Model|Authenticatable $record): string
    {
        $initials = htmlspecialchars(UserDirectory::initials(Filament::getNameForDefaultAvatar($record)), ENT_QUOTES | ENT_XML1, 'UTF-8');
        $svg = '<svg xmlns="http://www.w3.org/2000/svg" width="80" height="80" viewBox="0 0 80 80"><rect width="80" height="80" rx="40" fill="#18181b"/><text x="40" y="42" text-anchor="middle" dominant-baseline="middle" font-family="sans-serif" font-size="28" fill="#fff">'.$initials.'</text></svg>';

        return 'data:image/svg+xml;base64,'.base64_encode($svg);
    }
}
