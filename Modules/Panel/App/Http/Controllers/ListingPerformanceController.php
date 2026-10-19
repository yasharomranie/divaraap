<?php

declare(strict_types=1);

namespace Modules\Panel\App\Http\Controllers;

use App\Http\Controllers\Controller;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Modules\Admin\App\Settings\OperationsSettings;
use Modules\Conversation\App\Models\Conversation;
use Modules\Favorite\App\Models\FavoriteListing;
use Modules\Listing\Models\Listing;
use Modules\Listing\Models\ListingPerformanceEvent;
use Modules\Offer\Models\Offer;

final class ListingPerformanceController extends Controller
{
    public function show(Request $request, Listing $listing): Response
    {
        $listing->assertOwnedBy($request->user());
        $validated = $request->validate(['days' => ['nullable', 'integer', 'in:7,30,90']]);
        $days = (int) ($validated['days'] ?? 30);
        $end = CarbonImmutable::now(config('app.timezone'))->endOfDay();
        $start = $end->startOfDay()->subDays($days - 1);
        $report = ListingPerformanceEvent::reportFor($listing, $start, $end);
        $sellerId = (int) $request->user()->getKey();
        $listingId = (int) $listing->getKey();
        $metrics = [
            'views' => $report['views'], 'contacts' => $report['contacts'],
            'conversations' => Conversation::performanceCount($listingId, $sellerId, $start, $end),
            'offers' => Offer::performanceCount($listingId, $sellerId, $start, $end),
        ];
        $favorites = FavoriteListing::countsForListings([$listingId])[$listingId] ?? 0;
        $telemetryEnabled = app(OperationsSettings::class)->telemetry_enabled;

        return response()->view('panel::listing-performance', compact('listing', 'days', 'start', 'end', 'report', 'metrics', 'favorites', 'telemetryEnabled'))
            ->header('Cache-Control', 'private, no-store')->header('X-Robots-Tag', 'noindex, nofollow');
    }
}
