<?php

declare(strict_types=1);

namespace Modules\Listing\Filament\Admin\Resources\ListingQuestionResource\Pages;

use Filament\Resources\Pages\ListRecords;
use Modules\Listing\Filament\Admin\Resources\ListingQuestionResource;

final class ListListingQuestions extends ListRecords
{
    protected static string $resource = ListingQuestionResource::class;
}
