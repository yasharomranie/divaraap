<?php

declare(strict_types=1);

namespace Modules\Support\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use Illuminate\Support\Str;
use Modules\Support\Http\Requests\CreateTicketRequest;
use Modules\Support\Http\Requests\ReplyTicketRequest;
use Modules\Support\Models\SupportTicket;

final class SupportController extends Controller
{
    public function index(): Response
    {
        $actor = SupportTicket::actor();

        return $this->page('index', ['tickets' => SupportTicket::query()->where('user_id', $actor->id)->latest('last_activity_at')->paginate(15)]);
    }

    public function create(): Response
    {
        SupportTicket::actor();

        return $this->page('create', ['key' => (string) Str::uuid()]);
    }

    public function store(CreateTicketRequest $request): RedirectResponse
    {
        $ticket = SupportTicket::openTicket($request->validated('subject'), $request->validated('category'), $request->validated('body'), $request->validated('form_key'));

        return redirect()->route('support.show', $ticket)->with('success', __('support::messages.created'));
    }

    public function show(string $ticket): Response
    {
        $record = SupportTicket::query()->where('user_id', SupportTicket::actor()->id)->findOrFail($ticket);

        return $this->page('show', ['ticket' => $record, 'messages' => $record->messages()->where('kind', '!=', 'internal')->latest('id')->paginate(20), 'key' => (string) Str::uuid()]);
    }

    public function reply(ReplyTicketRequest $request, string $ticket): RedirectResponse
    {
        $record = SupportTicket::query()->where('user_id', SupportTicket::actor()->id)->findOrFail($ticket);
        $record->write($request->validated('action'), $request->validated('action') === 'closed' ? __('support::messages.customer_closed') : $request->validated('body'), (int) $request->validated('version'), $request->validated('operation_key'));

        return redirect()->route('support.show', $record)->with('success', __('support::messages.saved'));
    }

    private function page(string $view, array $data): Response
    {
        return response()->view('support::'.$view, $data)->header('Cache-Control', 'private, no-store')->header('X-Robots-Tag', 'noindex, nofollow');
    }
}
