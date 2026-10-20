<?php

declare(strict_types=1);

namespace Modules\Panel\App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Modules\Listing\Models\Listing;

final class ListingBulkController extends Controller
{
    public function preview(Request $request)
    {
        $data = $request->validate([
            'action' => ['required', Rule::in(['pause', 'resume', 'sold', 'delete'])],
            'ids' => ['required', 'array', 'min:1', 'max:50'],
            'ids.*' => ['required', 'integer', 'min:1', 'max:999999999999999999', 'distinct'],
        ]);
        $listings = Listing::bulkSelectionFor($request->user(), array_map(intval(...), $data['ids']), $data['action']);
        $ticket = Crypt::encryptString(json_encode([
            'owner' => (int) $request->user()->getKey(),
            'action' => $data['action'],
            'expires' => now()->addMinutes(10)->timestamp,
            'versions' => $listings->mapWithKeys(fn (Listing $listing) => [$listing->getKey() => $listing->bulkVersion()])->all(),
        ], JSON_THROW_ON_ERROR));

        return response()->view('panel::listing-bulk', ['listings' => $listings, 'action' => $data['action'], 'ticket' => $ticket])
            ->header('Cache-Control', 'private, no-store')->header('X-Robots-Tag', 'noindex, nofollow');
    }

    public function execute(Request $request)
    {
        try {
            $data = $request->validate(['ticket' => ['required', 'string', 'max:20000'], 'confirmed' => ['accepted']]);
            try {
                $ticket = json_decode(Crypt::decryptString($data['ticket']), true, 512, JSON_THROW_ON_ERROR);
            } catch (DecryptException|\JsonException) {
                throw ValidationException::withMessages(['selection' => __('listing::bulk.stale')]);
            }
            abort_unless(($ticket['owner'] ?? null) === (int) $request->user()->getKey(), 403);
            if (($ticket['expires'] ?? 0) <= now()->timestamp) {
                throw ValidationException::withMessages(['selection' => __('listing::bulk.stale')]);
            }
            $count = Listing::applyBulkFor($request->user(), $ticket['action'], $ticket['versions']);

            return redirect()->route('panel.listings.index')->with('success', __('listing::bulk.success', ['count' => $count]));
        } catch (ValidationException $exception) {
            return redirect()->route('panel.listings.index')->withErrors($exception->errors());
        }
    }
}
