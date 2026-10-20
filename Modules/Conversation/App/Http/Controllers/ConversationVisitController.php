<?php

declare(strict_types=1);

namespace Modules\Conversation\App\Http\Controllers;

use App\Http\Controllers\Controller;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Modules\Conversation\App\Models\Conversation;
use Modules\Conversation\App\Models\ConversationBlock;
use Modules\Conversation\App\Models\ConversationVisit;

final class ConversationVisitController extends Controller
{
    public function index(Request $request, Conversation $conversation): Response
    {
        $visits = ConversationVisit::forConversation($conversation, (int) $request->user()->getKey());

        return response()->view('conversation::visits', [
            'conversation' => $conversation, 'visits' => $visits, 'hasUpcoming' => ConversationVisit::hasUpcomingFor($conversation),
            'communicationBlocked' => ConversationBlock::between((int) $conversation->buyer_id, (int) $conversation->seller_id),
        ])->header('Cache-Control', 'private, no-store')->header('X-Robots-Tag', 'noindex, nofollow');
    }

    public function store(Request $request, Conversation $conversation): RedirectResponse
    {
        abort_unless($conversation->hasParticipant((int) $request->user()->getKey()), 403);
        $data = $request->validate([
            'visit_time' => ['required', 'date_format:Y-m-d\TH:i'],
            'meeting_place' => ['required', 'string', 'max:200'],
            'visit_note' => ['nullable', 'string', 'max:500'],
            'visit_confirmed' => ['accepted'],
        ]);
        $time = CarbonImmutable::createFromFormat('!Y-m-d\TH:i', $data['visit_time'], 'Asia/Tehran')->setTimezone(config('app.timezone'));
        ConversationVisit::proposeFor($conversation, (int) $request->user()->getKey(), $time, $data['meeting_place'], $data['visit_note'] ?? null);

        return redirect()->route('conversations.visits.index', $conversation)->with('success', __('conversation::visits.saved'));
    }

    public function decide(Request $request, Conversation $conversation, ConversationVisit $visit): RedirectResponse
    {
        abort_unless($conversation->hasParticipant((int) $request->user()->getKey()), 403);
        abort_unless($visit->matches($conversation), 404);
        $data = $request->validate(['decision' => ['required', 'in:accepted,declined,cancelled'], 'visit_confirmed' => ['accepted']]);
        $visit->decideFor($conversation, (int) $request->user()->getKey(), $data['decision']);

        return redirect()->route('conversations.visits.index', $conversation)->with('success', __('conversation::visits.updated'));
    }
}
