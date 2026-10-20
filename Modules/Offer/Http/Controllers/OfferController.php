<?php

declare(strict_types=1);

namespace Modules\Offer\Http\Controllers;

use App\Rules\MonetaryAmount;
use App\Support\ListingDirectory;
use App\Support\Money;
use App\Support\UserDirectory;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\Offer\Models\Offer;

class OfferController extends Controller
{
    public function index(Request $request): View
    {
        $userId = (int) $request->user()->getKey();
        $status = (string) $request->query('status', 'all');
        $direction = $request->query('direction') === 'sent' ? 'sent' : 'received';

        $offers = $direction === 'sent'
            ? Offer::sentByBuyer($userId)
            : Offer::receivedBySeller($userId, $status);

        $listings = ListingDirectory::resolve(
            $offers->getCollection()->map(static fn (Offer $offer): int => $offer->listingId())->all()
        );

        $counterpartIds = $offers->getCollection()
            ->map(static fn (Offer $offer): int => $direction === 'sent'
                ? (int) $offer->getAttribute('seller_id')
                : $offer->buyerId())
            ->all();

        return view('offer::index', [
            'offers' => $offers,
            'listings' => $listings,
            'people' => UserDirectory::resolve($counterpartIds),
            'counts' => Offer::statusCountsForSeller($userId),
            'status' => $status,
            'direction' => $direction,
        ]);
    }

    public function store(Request $request, int $listing): RedirectResponse
    {
        $summary = ListingDirectory::find($listing);

        if ($summary === null || $summary['seller_id'] === null || in_array($summary['status'], ['paused', 'scheduled'], true)) {
            abort(404);
        }

        $validated = $request->validate([
            'amount' => ['required', new MonetaryAmount($summary['currency']), function (string $attribute, mixed $value, \Closure $fail): void {
                if (Money::valid($value, 'USD') && Money::decimal($value) === '0.00') {
                    $fail(__('validation.gt.numeric', ['attribute' => __('offer::messages.your_offer'), 'value' => 0]));
                }
            }],
            'message' => ['nullable', 'string', 'max:500'],
        ]);

        $buyerId = (int) $request->user()->getKey();

        if ($buyerId === $summary['seller_id']) {
            return back()->with('error', __('offer::messages.cannot_offer_own'));
        }

        Offer::place(
            $listing,
            $buyerId,
            $summary['seller_id'],
            Money::decimal($validated['amount']),
            $summary['currency'],
            $validated['message'] ?? null,
        );

        return back()->with('success', __('offer::messages.sent'));
    }

    public function accept(Request $request, Offer $offer): RedirectResponse
    {
        $offer->respondFor((int) $request->user()->getKey(), Offer::STATUS_ACCEPTED);

        return back()->with('success', __('offer::messages.accepted'));
    }

    public function decline(Request $request, Offer $offer): RedirectResponse
    {
        $offer->respondFor((int) $request->user()->getKey(), Offer::STATUS_DECLINED);

        return back()->with('success', __('offer::messages.declined'));
    }

    public function withdraw(Request $request, Offer $offer): RedirectResponse
    {
        $offer->respondFor((int) $request->user()->getKey(), Offer::STATUS_WITHDRAWN);

        return back()->with('success', __('offer::messages.withdrawn'));
    }
}
