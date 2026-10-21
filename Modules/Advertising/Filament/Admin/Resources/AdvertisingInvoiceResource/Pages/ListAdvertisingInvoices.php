<?php

declare(strict_types=1);

namespace Modules\Advertising\Filament\Admin\Resources\AdvertisingInvoiceResource\Pages;

use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Modules\Advertising\Filament\Admin\Resources\AdvertisingInvoiceResource;

final class ListAdvertisingInvoices extends ListRecords
{
    protected static string $resource = AdvertisingInvoiceResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
