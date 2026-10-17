<?php

declare(strict_types=1);

namespace Modules\Advertising\Filament\Admin\Resources\BannerResource\Pages;

use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Modules\Advertising\Filament\Admin\Resources\BannerResource;

final class ListBanners extends ListRecords
{
    protected static string $resource = BannerResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
