<?php

declare(strict_types=1);

namespace Modules\Advertising\Filament\Admin\Resources\AdvertisingBundleResource\Pages;

use Filament\Resources\Pages\EditRecord;
use Modules\Advertising\Filament\Admin\Resources\AdvertisingBundleResource;

final class EditBundle extends EditRecord
{
    protected static string $resource = AdvertisingBundleResource::class;
}
