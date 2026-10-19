<?php

declare(strict_types=1);

namespace Modules\Panel\App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Modules\Listing\Models\Listing;

final class ListingAvailabilityController extends Controller
{
    public function pause(Request $request, Listing $listing)
    {
        $listing->changeDisplayFor($request->user(), true);

        return back()->with('success', __('listing::availability.paused_success'));
    }

    public function resume(Request $request, Listing $listing)
    {
        $listing->changeDisplayFor($request->user(), false);

        return back()->with('success', __('listing::availability.resumed_success'));
    }
}
