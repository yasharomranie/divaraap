<?php

declare(strict_types=1);

namespace Modules\Listing\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\Listing\Http\Requests\ListingQuestionRequest;
use Modules\Listing\Models\Listing;
use Modules\Listing\Models\ListingQuestion;

final class ListingQuestionController extends Controller
{
    public function index(Listing $listing)
    {
        return response()->view('listing::questions.index', ['listing' => $listing, 'records' => ListingQuestion::publishedFor($listing)])
            ->header('Cache-Control', 'private, no-store');
    }

    public function mine(Request $request)
    {
        $received = $request->query('role') === 'received';
        $records = ListingQuestion::forUser($request->user(), $received);
        $listings = Listing::withTrashed()->whereKey($records->pluck('listing_id'))->get(['id', 'title', 'user_id'])->keyBy('id');

        return response()->view('listing::questions.mine', compact('records', 'listings', 'received'))->header('Cache-Control', 'private, no-store')->header('X-Robots-Tag', 'noindex, nofollow');
    }

    public function store(ListingQuestionRequest $request, Listing $listing): RedirectResponse
    {
        ListingQuestion::ask($request->user(), $listing, $request->validated('question'), $request->boolean('public_consent'));

        return redirect()->route('listing-questions.mine')->with('success', __('listing::questions.sent'));
    }

    public function answer(ListingQuestionRequest $request, ListingQuestion $question): RedirectResponse
    {
        $question->answerFor($request->user(), $request->validated('answer'), (int) $request->validated('revision'));

        return redirect()->route('listing-questions.mine', ['role' => 'received'])->with('success', __('listing::questions.answer_saved'));
    }

    public function withdraw(ListingQuestionRequest $request, ListingQuestion $question): RedirectResponse
    {
        $question->withdrawFor($request->user(), (int) $request->validated('revision'));

        return redirect()->route('listing-questions.mine')->with('success', __('listing::questions.withdrawn'));
    }
}
