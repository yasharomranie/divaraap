<?php

declare(strict_types=1);

namespace Modules\Promotion\Filament\Admin\Resources\SellerPackageResource\Pages;

use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Modules\Promotion\Filament\Admin\Resources\SellerPackageResource;

final class ListPackages extends ListRecords
{
    protected static string $resource = SellerPackageResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
