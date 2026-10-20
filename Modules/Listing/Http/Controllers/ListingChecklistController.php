<?php

declare(strict_types=1);

namespace Modules\Listing\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Modules\Listing\Models\Listing;
use Modules\Listing\Models\ListingChecklistProgress;

final class ListingChecklistController extends Controller
{
    public function index(Request $request, Listing $listing): Response
    {
        $state = ListingChecklistProgress::stateFor($listing, $request->user());
        abort_if($state['items'] === [], 404);

        return response()->view('listing::checklist', compact('listing', 'state'))->header('Cache-Control', 'private, no-store')->header('X-Robots-Tag', 'noindex, nofollow');
    }

    public function save(Request $request, Listing $listing): RedirectResponse
    {
        $data = $request->validate([
            'revision' => ['required', 'string', 'size:64', 'regex:/^[a-f0-9]+$/'],
            'version' => ['required', 'string', 'max:36'],
            'checked' => ['sometimes', 'array', 'max:20'],
            'checked.*' => ['required', 'string', 'size:64', 'distinct', 'regex:/^[a-f0-9]+$/'],
        ]);
        ListingChecklistProgress::saveFor($listing, $request->user(), $data['revision'], $data['version'], $data['checked'] ?? []);

        return redirect()->route('listings.checklist', $listing)->with('success', __('category::checklists.saved'));
    }
}
