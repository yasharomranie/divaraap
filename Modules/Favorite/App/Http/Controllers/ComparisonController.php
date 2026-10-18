<?php

declare(strict_types=1);

namespace Modules\Favorite\App\Http\Controllers;

use Illuminate\Http\Request;
use Modules\Favorite\App\Support\ListingComparison;
use Modules\Listing\Models\Listing;

final class ComparisonController
{
    public function index(Request $request, ListingComparison $comparison)
    {
        $listings = $comparison->listings();
        $rows = $comparison->rows($listings);
        $differences = $request->boolean('differences') && $listings->count() > 1;
        if ($differences) {
            $rows = array_values(array_filter($rows, fn (array $row) => $row['different']));
        }

        return response()->view('favorite::comparison', compact('listings', 'rows', 'differences'))->header('Cache-Control', 'private, no-store')->header('X-Robots-Tag', 'noindex, nofollow');
    }

    public function add(Listing $listing, ListingComparison $comparison)
    {
        $comparison->add($listing);

        return redirect()->route('comparison.index')->with('success', __('favorite::comparison.added'));
    }

    public function remove(int $listing, ListingComparison $comparison)
    {
        $comparison->remove($listing);

        return redirect()->route('comparison.index');
    }

    public function clear()
    {
        session()->forget('listing_comparison');

        return redirect()->route('comparison.index');
    }
}
