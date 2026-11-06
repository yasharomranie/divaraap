<?php

declare(strict_types=1);

namespace Modules\Support\Filament\Admin\Resources\SupportTicketResource\Pages;

use Filament\Resources\Pages\ListRecords;
use Modules\Support\Filament\Admin\Resources\SupportTicketResource;

final class ListSupportTickets extends ListRecords
{
    protected static string $resource = SupportTicketResource::class;
}
