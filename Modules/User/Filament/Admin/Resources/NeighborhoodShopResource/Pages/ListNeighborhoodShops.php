<?php

declare(strict_types=1);

namespace Modules\User\Filament\Admin\Resources\NeighborhoodShopResource\Pages;

use Filament\Actions\Action;
use Filament\Resources\Pages\ListRecords;
use Modules\User\App\Models\NeighborhoodShop;
use Modules\User\Filament\Admin\Resources\NeighborhoodShopResource;

final class ListNeighborhoodShops extends ListRecords
{
    protected static string $resource = NeighborhoodShopResource::class;

    protected function getHeaderActions(): array
    {
        return [Action::make('add')->label(__('user::neighborhoods.add'))->schema(NeighborhoodShopResource::fields())->modalDescription(__('user::neighborhoods.review_hint'))
            ->action(fn (array $data) => NeighborhoodShop::saveSelection(auth()->user(), $data))];
    }
}
