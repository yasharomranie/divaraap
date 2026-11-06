<?php

declare(strict_types=1);

namespace Modules\Notification\Filament\Admin\Resources\EmailDeliveryResource\Pages;

use Filament\Resources\Pages\ListRecords;
use Modules\Notification\Filament\Admin\Resources\EmailDeliveryResource;

final class ListEmailDeliveries extends ListRecords
{
    protected static string $resource = EmailDeliveryResource::class;
}
