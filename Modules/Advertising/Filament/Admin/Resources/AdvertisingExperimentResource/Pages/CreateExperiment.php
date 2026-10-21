<?php

declare(strict_types=1);

namespace Modules\Advertising\Filament\Admin\Resources\AdvertisingExperimentResource\Pages;

use Filament\Resources\Pages\CreateRecord;
use Modules\Advertising\Filament\Admin\Resources\AdvertisingExperimentResource;

final class CreateExperiment extends CreateRecord
{
    protected static string $resource = AdvertisingExperimentResource::class;

    protected function getRedirectUrl(): string
    {
        return self::getResource()::getUrl('index');
    }
}
