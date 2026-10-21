<?php

declare(strict_types=1);

namespace Modules\Promotion\Filament\Admin\Resources\SellerPackageResource\Pages;

use Filament\Resources\Pages\EditRecord;
use Modules\Promotion\Filament\Admin\Resources\SellerPackageResource;

final class EditPackage extends EditRecord
{
    protected static string $resource = SellerPackageResource::class;
}
