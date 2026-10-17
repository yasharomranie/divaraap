<?php

declare(strict_types=1);

namespace Modules\Advertising\Filament\Admin\Resources\BannerPlacementResource\Pages;

use Filament\Resources\Pages\ListRecords;
use Modules\Advertising\Filament\Admin\Resources\BannerPlacementResource;

final class ListPlacements extends ListRecords
{
    protected static string $resource = BannerPlacementResource::class;
}
