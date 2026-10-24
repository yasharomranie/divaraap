<?php

declare(strict_types=1);

namespace Modules\Listing\Http\Controllers;

use Illuminate\Http\Response;
use Modules\Category\Models\Category;
use Modules\Listing\Http\Requests\PriceGuideRequest;
use Modules\Listing\Models\ListingCustomField;
use Modules\Listing\Support\ListingPanelHelper;
use Modules\Listing\Support\PriceGuide;
use Modules\Location\Models\City;

final class PriceGuideController
{
    public function index(PriceGuideRequest $request): Response
    {
        $filters = $request->validated();
        $category = filled($filters['category_id'] ?? null) ? (int) $filters['category_id'] : null;
        $report = $category ? PriceGuide::report($filters) : null;

        return response()->view('listing::prices.index', ['filters' => $filters, 'report' => $report, 'categories' => Category::priceGuideOptions(), 'cities' => City::wantedOptions(), 'currencies' => ListingPanelHelper::currencyOptions(), 'conditions' => ListingCustomField::priceGuideConditions($category)])
            ->header('Cache-Control', 'private, no-store')->header('X-Robots-Tag', 'noindex, nofollow');
    }
}
