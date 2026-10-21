<?php

declare(strict_types=1);

namespace Modules\Advertising\Filament\Admin\Resources\AdvertisingBundleResource\Pages;

use Filament\Resources\Pages\CreateRecord;
use Modules\Advertising\Filament\Admin\Resources\AdvertisingBundleResource;

final class CreateBundle extends CreateRecord
{
    protected static string $resource = AdvertisingBundleResource::class;
}
