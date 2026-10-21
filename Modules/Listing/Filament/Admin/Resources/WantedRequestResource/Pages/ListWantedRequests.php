<?php

declare(strict_types=1);

namespace Modules\Listing\Filament\Admin\Resources\WantedRequestResource\Pages;

use Filament\Resources\Pages\ListRecords;
use Modules\Listing\Filament\Admin\Resources\WantedRequestResource;

final class ListWantedRequests extends ListRecords
{
    protected static string $resource = WantedRequestResource::class;
}
