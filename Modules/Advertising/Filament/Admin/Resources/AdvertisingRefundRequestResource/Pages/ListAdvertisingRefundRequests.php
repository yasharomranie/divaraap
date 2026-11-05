<?php

declare(strict_types=1);

namespace Modules\Advertising\Filament\Admin\Resources\AdvertisingRefundRequestResource\Pages;

use Filament\Resources\Pages\ListRecords;
use Modules\Advertising\Filament\Admin\Resources\AdvertisingRefundRequestResource;

final class ListAdvertisingRefundRequests extends ListRecords
{
    protected static string $resource = AdvertisingRefundRequestResource::class;
}
