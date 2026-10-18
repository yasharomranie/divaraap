<?php

declare(strict_types=1);

namespace Modules\Favorite\App\Http\Controllers;

use Illuminate\Http\Request;
use Modules\Favorite\App\Support\ListingHistory;

final class HistoryController
{
    public function index(ListingHistory $history)
    {
        return response()->view('favorite::history', $history->page())->header('Cache-Control', 'private, no-store')->header('X-Robots-Tag', 'noindex, nofollow');
    }

    public function remove(int $listing, ListingHistory $history)
    {
        $history->remove($listing);

        return redirect()->route('history.index');
    }

    public function clear(ListingHistory $history)
    {
        $history->clear();

        return redirect()->route('history.index')->with('success', __('favorite::history.cleared'));
    }

    public function configure(Request $request, ListingHistory $history)
    {
        $data = $request->validate(['enabled' => ['required', 'boolean']]);
        $history->configure((bool) $data['enabled']);

        return redirect()->route('history.index')->with('success', __('favorite::history.saved'));
    }
}
