<?php

declare(strict_types=1);

namespace Modules\Advertising\Filament\Admin\Resources\PlacementReservationResource\Pages;

use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Modules\Advertising\Filament\Admin\Pages\PlacementCalendar;
use Modules\Advertising\Filament\Admin\Resources\PlacementReservationResource;

final class ListReservations extends ListRecords
{
    protected static string $resource = PlacementReservationResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make(), Action::make('calendar')->label(__('advertising::reservations.calendar'))->url(PlacementCalendar::getUrl())];
    }
}
