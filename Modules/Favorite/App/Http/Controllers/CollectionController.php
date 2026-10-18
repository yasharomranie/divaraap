<?php

declare(strict_types=1);

namespace Modules\Favorite\App\Http\Controllers;

use Illuminate\Http\Request;
use Modules\Favorite\App\Models\FavoriteCollection;
use Modules\Favorite\App\Models\FavoriteListing;

final class CollectionController
{
    public function index(Request $request)
    {
        return response()->view('favorite::collections', ['collections' => FavoriteCollection::optionsFor($request->user()->id)])->header('Cache-Control', 'private, no-store')->header('X-Robots-Tag', 'noindex, nofollow');
    }

    public function save(Request $request, ?int $collection = null)
    {
        abort_unless((string) $request->user()->status === 'active', 403);
        $request->merge(['editing_collection' => $collection]);
        $data = $request->validate(['name' => ['required', 'string', 'max:60']]);
        FavoriteCollection::saveFor($request->user(), $data['name'], $collection);

        return redirect()->route('collections.index')->with('success', __('favorite::collections.saved'));
    }

    public function remove(Request $request, int $collection)
    {
        abort_unless((string) $request->user()->status === 'active', 403);
        FavoriteCollection::removeFor($request->user(), $collection);

        return redirect()->route('collections.index')->with('success', __('favorite::collections.deleted'));
    }

    public function move(Request $request, int $listing)
    {
        abort_unless((string) $request->user()->status === 'active', 403);
        $data = $request->validate(['collection_id' => ['nullable', 'integer', 'min:1']]);
        FavoriteListing::moveFor($request->user(), $listing, filled($data['collection_id'] ?? null) ? (int) $data['collection_id'] : null);

        return back()->with('success', __('favorite::collections.moved'));
    }
}
