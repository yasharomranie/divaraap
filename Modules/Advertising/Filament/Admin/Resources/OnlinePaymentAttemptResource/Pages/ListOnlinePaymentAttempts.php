<?php

declare(strict_types=1);

namespace Modules\Advertising\Filament\Admin\Resources\OnlinePaymentAttemptResource\Pages;

use Filament\Resources\Pages\ListRecords;
use Modules\Advertising\Filament\Admin\Resources\OnlinePaymentAttemptResource;

final class ListOnlinePaymentAttempts extends ListRecords
{
    protected static string $resource = OnlinePaymentAttemptResource::class;
}
