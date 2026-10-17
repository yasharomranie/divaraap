<?php

declare(strict_types=1);

namespace Modules\Advertising\Filament\Admin\Resources\BannerResource\Pages;

use Filament\Resources\Pages\EditRecord;
use Modules\Advertising\Filament\Admin\Resources\BannerResource;

final class EditBanner extends EditRecord
{
    protected static string $resource = BannerResource::class;
}
