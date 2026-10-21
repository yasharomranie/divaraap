<?php

declare(strict_types=1);

namespace Modules\Advertising\Filament\Admin\Resources\PlacementReservationResource\Pages;

use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Modules\Advertising\Filament\Admin\Resources\PlacementReservationResource;
use Modules\Advertising\Models\PlacementReservation;

final class CreateReservation extends CreateRecord
{
    protected static string $resource = PlacementReservationResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        return PlacementReservation::reserve($data);
    }

    protected function getRedirectUrl(): string
    {
        return PlacementReservationResource::getUrl('index');
    }
}
