<?php

declare(strict_types=1);

namespace Modules\Conversation\App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Support\UserDirectory;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Modules\Conversation\App\Models\Conversation;
use Modules\Conversation\App\Models\ConversationBlock;
use Modules\Report\Models\Report;

final class ConversationSafetyController extends Controller
{
    private function target(Request $request, Conversation $conversation): int
    {
        abort_unless($conversation->hasParticipant((int) $request->user()->getKey()), 403);

        return (int) ((int) $conversation->buyer_id === (int) $request->user()->getKey() ? $conversation->seller_id : $conversation->buyer_id);
    }

    public function show(Request $request, Conversation $conversation)
    {
        $target = $this->target($request, $conversation);
        $messages = $conversation->messages()->where('sender_id', $target)->where('created_at', '<=', now())->latest('id')->limit(50)->get(['id', 'body']);

        return response()->view('conversation::safety', ['conversation' => $conversation, 'target' => $target, 'partner' => UserDirectory::find($target), 'messages' => $messages,
            'blockedByMe' => ConversationBlock::owned((int) $request->user()->getKey(), $target), 'communicationBlocked' => ConversationBlock::between((int) $request->user()->getKey(), $target),
        ])->header('Cache-Control', 'private, no-store')->header('X-Robots-Tag', 'noindex, nofollow');
    }

    public function block(Request $request, Conversation $conversation)
    {
        $target = $this->target($request, $conversation);
        $data = $request->validate(['confirmed' => ['accepted'], 'blocked' => ['required', 'boolean']]);
        ConversationBlock::setFor($request->user(), $target, (bool) $data['blocked']);

        return redirect()->route('conversations.safety', $conversation)->with('success', __('conversation::safety.saved'));
    }

    public function report(Request $request, Conversation $conversation)
    {
        $this->target($request, $conversation);
        $data = $request->validate(['confirmed' => ['accepted'], 'message_id' => ['nullable', 'integer', 'min:1', 'max:999999999999999999'], 'reason' => ['required', Rule::in(Report::conversationReasons())], 'details' => ['nullable', 'string', 'max:1000']]);
        $report = Report::fileConversationFor($request->user(), $conversation, isset($data['message_id']) ? (int) $data['message_id'] : null, $data['reason'], $data['details'] ?? null);

        return redirect()->route('conversations.safety', $conversation)->with('success', $report->wasRecentlyCreated ? __('conversation::safety.reported') : __('report::messages.already_reported'));
    }

    public function blocked(Request $request)
    {
        $blocks = ConversationBlock::forOwner($request->user());

        return response()->view('conversation::blocked', ['blocks' => $blocks, 'users' => UserDirectory::resolve($blocks->getCollection()->pluck('blocked_id')->all())])
            ->header('Cache-Control', 'private, no-store')->header('X-Robots-Tag', 'noindex, nofollow');
    }

    public function unblock(Request $request, int $target)
    {
        $request->validate(['confirmed' => ['accepted']]);
        ConversationBlock::setFor($request->user(), $target, false);

        return redirect()->route('panel.blocked-users')->with('success', __('conversation::safety.saved'));
    }
}
