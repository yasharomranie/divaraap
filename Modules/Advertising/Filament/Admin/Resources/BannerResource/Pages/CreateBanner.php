<?php

declare(strict_types=1);

namespace Modules\Advertising\Filament\Admin\Resources\BannerResource\Pages;

use Filament\Resources\Pages\CreateRecord;
use Modules\Advertising\Filament\Admin\Resources\BannerResource;

final class CreateBanner extends CreateRecord
{
    protected static string $resource = BannerResource::class;
}
