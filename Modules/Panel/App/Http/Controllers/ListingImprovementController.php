<?php

declare(strict_types=1);

namespace Modules\Panel\App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Modules\Listing\Models\Listing;

final class ListingImprovementController extends Controller
{
    public function show(Request $request, Listing $listing)
    {
        $listing->assertOwnedBy($request->user());
        $guide = $listing->improvementGuide();

        return response()->view('panel::listing-improvement', compact('listing', 'guide'))
            ->header('Cache-Control', 'private, no-store')->header('X-Robots-Tag', 'noindex, nofollow');
    }

    public function photo(Request $request, Listing $listing)
    {
        $listing->assertOwnedBy($request->user());
        abort_unless((string) $request->user()->status === 'active', 403);
        try {
            $request->validate(['photo' => ['required', 'image', 'mimes:jpg,jpeg,png', 'max:'.config('quick-listing.max_photo_size_kb', 5120), 'dimensions:max_width=6000,max_height=6000']]);
            $listing->addPanelPhoto($request->user(), $request->file('photo'));
        } catch (ValidationException $exception) {
            throw $exception->redirectTo(route('panel.listings.edit', $listing).'#listing-images');
        }

        return redirect()->route('panel.listings.improve', $listing)->with('success', __('listing::improvement.photo_added'));
    }
}
