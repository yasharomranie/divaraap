<?php

declare(strict_types=1);

namespace Modules\Advertising\Filament\Admin\Resources\CampaignResource\Pages;

use Filament\Resources\Pages\EditRecord;
use Modules\Advertising\Filament\Admin\Resources\CampaignResource;

final class EditCampaign extends EditRecord
{
    protected static string $resource = CampaignResource::class;
}
