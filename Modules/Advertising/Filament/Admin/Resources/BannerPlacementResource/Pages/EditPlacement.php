<?php

declare(strict_types=1);

namespace Modules\Advertising\Filament\Admin\Resources\BannerPlacementResource\Pages;

use Filament\Resources\Pages\EditRecord;
use Modules\Advertising\Filament\Admin\Resources\BannerPlacementResource;

final class EditPlacement extends EditRecord
{
    protected static string $resource = BannerPlacementResource::class;
}
