<?php

declare(strict_types=1);

namespace Modules\Listing\States;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasDescription;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;

class PausedListingStatus extends ListingStatus implements HasColor, HasDescription, HasIcon, HasLabel
{
    protected static string $name = 'paused';

    public function getLabel(): ?string
    {
        return __('listing::availability.paused');
    }

    public function getColor(): string|array|null
    {
        return 'warning';
    }

    public function getIcon(): ?string
    {
        return 'heroicon-o-pause-circle';
    }

    public function getDescription(): ?string
    {
        return __('listing::availability.hint');
    }
}
