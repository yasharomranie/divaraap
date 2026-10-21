<?php

declare(strict_types=1);

namespace Modules\Advertising\Filament\Admin\Resources\AdvertisingBundleResource\Pages;

use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Modules\Advertising\Filament\Admin\Resources\AdvertisingBundleResource;

final class ListBundles extends ListRecords
{
    protected static string $resource = AdvertisingBundleResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
