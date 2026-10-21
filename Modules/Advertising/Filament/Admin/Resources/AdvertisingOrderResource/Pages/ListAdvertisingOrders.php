<?php

declare(strict_types=1);

namespace Modules\Advertising\Filament\Admin\Resources\AdvertisingOrderResource\Pages;

use Filament\Resources\Pages\ListRecords;
use Modules\Advertising\Filament\Admin\Resources\AdvertisingOrderResource;

final class ListAdvertisingOrders extends ListRecords
{
    protected static string $resource = AdvertisingOrderResource::class;
}
