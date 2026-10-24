<?php

declare(strict_types=1);

namespace Modules\Favorite\App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Pagination\LengthAwarePaginator;
use Modules\Favorite\App\Http\Requests\ShortlistSharingRequest;
use Modules\Favorite\App\Models\FavoriteCollection;

final class ShortlistSharingController
{
    public function manage(Request $request, int $collection): Response
    {
        FavoriteCollection::sharingActor($request->user());
        $record = FavoriteCollection::assertOwned($request->user()->id, $collection);

        return $this->view('favorite::sharing.manage', ['record' => $record, 'preview' => $record->sharingPreview($request->user())]);
    }

    public function change(ShortlistSharingRequest $request, int $collection): RedirectResponse
    {
        $record = FavoriteCollection::assertOwned($request->user()->id, $collection);
        $record->changeSharingFor($request->user(), $request->validated());

        return redirect()->route('shortlists.manage', $record)->with('status', __('favorite::sharing.saved'));
    }

    public function shared(string $token): Response
    {
        $record = FavoriteCollection::sharedShortlist($token);
        $listings = $record->sharedItems();
        abort_if($listings->isEmpty(), 404);
        $page = LengthAwarePaginator::resolveCurrentPage();
        $items = new LengthAwarePaginator($listings->forPage($page, 12)->values(), $listings->count(), 12, $page, ['path' => LengthAwarePaginator::resolveCurrentPath()]);

        return $this->view('favorite::sharing.shared', ['items' => $items, 'expires' => $record->share_expires_at, 'missing' => $listings->count() < count($record->share_ids)]);
    }

    private function view(string $view, array $data): Response
    {
        return response()->view($view, $data)->header('Cache-Control', 'private, no-store')->header('X-Robots-Tag', 'noindex, nofollow')->header('Referrer-Policy', 'no-referrer');
    }
}
