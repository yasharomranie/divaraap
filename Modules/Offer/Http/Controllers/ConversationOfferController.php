<?php

declare(strict_types=1);

namespace Modules\Offer\Http\Controllers;

use App\Rules\MonetaryAmount;
use App\Support\ListingDirectory;
use App\Support\Money;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\Conversation\App\Models\Conversation;
use Modules\Offer\Models\Offer;

final class ConversationOfferController extends Controller
{
    public function snapshot(Request $request, Conversation $conversation): JsonResponse
    {
        $offers = Offer::inConversation($conversation, (int) $request->user()->getKey());

        return response()->json([
            'html' => view('offer::partials.chat-cards', ['chatOffers' => $offers, 'selectedConversation' => $conversation])->render(),
        ])->header('Cache-Control', 'no-store, private');
    }

    public function store(Request $request, Conversation $conversation): RedirectResponse
    {
        abort_unless((int) $conversation->buyer_id === (int) $request->user()->getKey(), 403);
        $listing = ListingDirectory::find((int) $conversation->listing_id);
        abort_unless($listing, 404);
        $data = $request->validate([
            'amount' => ['required', new MonetaryAmount($listing['currency'])],
            'message' => ['nullable', 'string', 'max:500'],
        ]);
        Offer::placeInConversation($conversation, (int) $request->user()->getKey(), Money::decimal($data['amount']), $data['message'] ?? null);

        return redirect()->route('panel.inbox.index', ['conversation' => $conversation->id])->with('success', __('offer::messages.sent'));
    }

    public function respond(Request $request, Conversation $conversation, Offer $offer): RedirectResponse
    {
        abort_unless($conversation->hasParticipant((int) $request->user()->getKey()), 403);
        abort_unless($offer->matchesConversation($conversation), 404);
        $data = $request->validate(['decision' => ['required', 'in:accepted,declined,withdrawn'], 'confirmed' => ['accepted']]);
        $offer->respondFor((int) $request->user()->getKey(), $data['decision']);

        return redirect()->route('panel.inbox.index', ['conversation' => $conversation->id])->with('success', __('offer::messages.'.$data['decision']));
    }
}
