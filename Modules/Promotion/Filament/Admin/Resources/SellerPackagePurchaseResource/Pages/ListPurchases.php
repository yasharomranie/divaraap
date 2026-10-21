<?php

declare(strict_types=1);

namespace Modules\Promotion\Filament\Admin\Resources\SellerPackagePurchaseResource\Pages;

use Filament\Resources\Pages\ListRecords;
use Modules\Promotion\Filament\Admin\Resources\SellerPackagePurchaseResource;

final class ListPurchases extends ListRecords
{
    protected static string $resource = SellerPackagePurchaseResource::class;
}
