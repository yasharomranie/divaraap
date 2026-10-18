<?php

declare(strict_types=1);

namespace Modules\Advertising\Filament\Admin\Resources\CampaignResource\Pages;

use Filament\Resources\Pages\CreateRecord;
use Modules\Advertising\Filament\Admin\Resources\CampaignResource;

final class CreateCampaign extends CreateRecord
{
    protected static string $resource = CampaignResource::class;
}
