<?php

declare(strict_types=1);

namespace Modules\Review\Http\Controllers;

use App\Support\UserDirectory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\Notification\Models\UserNotification;
use Modules\Review\Models\Review;

class ReviewController extends Controller
{
    public function index(Request $request)
    {
        $reviews = Review::writtenPageFor($request->user());

        return response()->view('review::mine', [
            'reviews' => $reviews,
            'sellers' => UserDirectory::resolve($reviews->getCollection()->pluck('seller_id')->all()),
        ])->header('Cache-Control', 'private, no-store')->header('X-Robots-Tag', 'noindex, nofollow');
    }

    public function store(Request $request, int $seller): RedirectResponse
    {
        $validated = $request->validate([
            'rating' => ['required', 'integer', 'min:'.Review::MIN_RATING, 'max:'.Review::MAX_RATING],
            'title' => ['nullable', 'string', 'max:120'],
            'body' => ['nullable', 'string', 'max:2000'],
            'listing_id' => ['nullable', 'integer', 'min:1', 'max:999999999999999999'],
        ]);

        $authorId = (int) $request->user()->getKey();

        if ($authorId === $seller) {
            return back()->with('error', __('review::messages.cannot_review_self'));
        }

        if (! UserDirectory::exists($seller)) {
            abort(404);
        }

        Review::submitFor(
            $request->user(), $seller, isset($validated['listing_id']) ? (int) $validated['listing_id'] : null,
            (int) $validated['rating'], $validated['title'] ?? null, $validated['body'] ?? null,
        );

        UserNotification::publish(
            $seller,
            UserNotification::TYPE_REVIEW,
            __('review::quality.received_pending'),
            __('review::messages.notification_body', ['name' => UserDirectory::nameFor($authorId)]),
            route('sellers.show', ['seller' => $seller]),
        );

        return redirect()->route('reviews.mine')->with('success', __('review::quality.submitted'));
    }

    public function destroy(Request $request, Review $review): RedirectResponse
    {
        abort_unless((string) $request->user()->status === 'active', 403);
        abort_unless($review->authorId() === (int) $request->user()->getKey(), 403);

        $review->delete();

        return back()->with('success', __('review::messages.deleted'));
    }
}
