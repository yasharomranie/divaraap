<?php

declare(strict_types=1);

namespace Modules\Listing\Http\Controllers;

use Illuminate\Http\Request;
use Modules\Listing\Models\Listing;

final class ListingLocationController
{
    public function index(Request $request, Listing $listing)
    {
        $listing->assertOwnedBy($request->user());
        $point = $listing->latitude !== null && $listing->longitude !== null ? ['lat' => round((float) $listing->latitude, 3), 'lng' => round((float) $listing->longitude, 3)] : null;

        return response()->view('listing::location', compact('listing', 'point'))->header('Cache-Control', 'private, no-store')->header('X-Robots-Tag', 'noindex, nofollow');
    }

    public function save(Request $request, Listing $listing)
    {
        $listing->assertOwnedBy($request->user());
        abort_unless((string) $request->user()->status === 'active', 403);
        $data = $request->validate(['latitude' => ['required', 'numeric', 'between:-85.05112878,85.05112878'], 'longitude' => ['required', 'numeric', 'between:-180,180']]);
        $listing->updateMapLocationFor($request->user(), round((float) $data['latitude'], 3), round((float) $data['longitude'], 3));

        return redirect()->route('listings.location', $listing)->with('success', __('listing::map.location_saved'));
    }

    public function remove(Request $request, Listing $listing)
    {
        $listing->assertOwnedBy($request->user());
        abort_unless((string) $request->user()->status === 'active', 403);
        $listing->updateMapLocationFor($request->user(), null, null);

        return redirect()->route('listings.location', $listing)->with('success', __('listing::map.location_removed'));
    }
}
