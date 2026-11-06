<x-filament-panels::page>
    @php($ticket = \Modules\Support\Models\SupportTicket::staffQuery()->findOrFail($this->getRecord()->id))
    @php($messages = $ticket->messages()->latest('id')->paginate(20))
    <x-filament::section>
        <div style="display:grid;gap:16px;min-width:0;overflow-wrap:anywhere">
            <h2 dir="auto" style="font-size:1.25rem;font-weight:600">{{ $ticket->subject }}</h2>
            <p>{{ __('support::messages.state_'.$ticket->state) }} · {{ __('support::messages.category_'.$ticket->category) }}</p>
            <p>{{ __('support::messages.owner') }} #{{ $ticket->user_id }} · {{ __('support::messages.assignee') }}: {{ $ticket->assignee_id ? '#'.$ticket->assignee_id : __('support::messages.unassigned') }}</p>
            @include('support::thread')
        </div>
    </x-filament::section>
</x-filament-panels::page>
