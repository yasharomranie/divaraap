<?php

declare(strict_types=1);

namespace Modules\Panel\App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Modules\Listing\Models\Listing;
use Modules\Listing\Models\ListingDraft;
use Modules\Listing\Support\DraftConflict;

final class ListingDuplicateController extends Controller
{
    public function show(Request $request, Listing $listing)
    {
        $listing->assertOwnedBy($request->user());
        $payload = $listing->duplicationPayload(false);
        $hasDraft = $this->hasDraft($request);
        $photoCount = count($listing->duplicationPhotos());
        $sourceVersion = $listing->duplicationVersion();

        return response()->view('panel::duplicate-listing', compact('listing', 'payload', 'hasDraft', 'photoCount', 'sourceVersion'))->header('Cache-Control', 'private, no-store')->header('X-Robots-Tag', 'noindex, nofollow');
    }

    public function store(Request $request, Listing $listing)
    {
        $listing->assertOwnedBy($request->user());
        abort_unless((string) $request->user()->status === 'active', 403);
        $data = $request->validate(['source_version' => ['required', 'string', 'size:64', 'regex:/^[a-f0-9]+$/'], 'photos' => ['sometimes', 'boolean'], 'location' => ['sometimes', 'boolean']]);
        if ($this->hasDraft($request)) {
            return back()->withErrors(['draft' => __('listing::duplicate.has_draft')]);
        }
        try {
            ListingDraft::fromListing($request->user(), $listing, $data['source_version'], $request->boolean('photos'), $request->boolean('location'));
        } catch (DraftConflict) {
            return back()->withErrors(['draft' => __('listing::duplicate.has_draft')]);
        }

        return redirect()->route('panel.listings.create')->with('success', __('listing::duplicate.created'));
    }

    private function hasDraft(Request $request): bool
    {
        return ListingDraft::currentFor($request->user()) !== null || filled($request->session()->get('panel_quick_listing_draft.'.$request->user()->getKey()));
    }
}
