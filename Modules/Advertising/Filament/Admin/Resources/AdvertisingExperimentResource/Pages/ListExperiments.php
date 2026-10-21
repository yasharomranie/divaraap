<?php

declare(strict_types=1);

namespace Modules\Advertising\Filament\Admin\Resources\AdvertisingExperimentResource\Pages;

use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Modules\Advertising\Filament\Admin\Resources\AdvertisingExperimentResource;

final class ListExperiments extends ListRecords
{
    protected static string $resource = AdvertisingExperimentResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
