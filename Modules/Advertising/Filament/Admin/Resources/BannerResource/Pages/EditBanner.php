<?php

declare(strict_types=1);

namespace Modules\Advertising\Filament\Admin\Resources\BannerResource\Pages;

use Filament\Actions\Action;
use Filament\Resources\Pages\EditRecord;
use Modules\Advertising\Filament\Admin\Pages\AdvertisingPreview;
use Modules\Advertising\Filament\Admin\Resources\BannerResource;

final class EditBanner extends EditRecord
{
    protected static string $resource = BannerResource::class;

    protected function getHeaderActions(): array
    {
        return [Action::make('preview')->label(__('advertising::preview.action'))->icon('heroicon-o-eye')->url(fn () => AdvertisingPreview::getUrl(['banner' => $this->record->getKey()]))];
    }
}
