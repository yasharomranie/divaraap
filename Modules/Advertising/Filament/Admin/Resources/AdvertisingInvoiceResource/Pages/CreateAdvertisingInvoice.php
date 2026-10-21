<?php

declare(strict_types=1);

namespace Modules\Advertising\Filament\Admin\Resources\AdvertisingInvoiceResource\Pages;

use Filament\Resources\Pages\CreateRecord;
use Modules\Advertising\Filament\Admin\Resources\AdvertisingInvoiceResource;

final class CreateAdvertisingInvoice extends CreateRecord
{
    protected ?bool $hasDatabaseTransactions = true;

    protected static string $resource = AdvertisingInvoiceResource::class;

    protected function getRedirectUrl(): string
    {
        return AdvertisingInvoiceResource::getUrl('index');
    }
}
