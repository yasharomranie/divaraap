<?php

declare(strict_types=1);

namespace Modules\Promotion\Filament\Admin\Resources\SellerPackageResource\Pages;

use Filament\Resources\Pages\CreateRecord;
use Modules\Promotion\Filament\Admin\Resources\SellerPackageResource;

final class CreatePackage extends CreateRecord
{
    protected static string $resource = SellerPackageResource::class;
}
