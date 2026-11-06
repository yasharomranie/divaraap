@extends('panel::layouts.panel', ['panelSection' => 'support'])
@section('title', __('support::messages.ticket'))
@section('panel_content')
<section class="card card__body stack support-ticket">
    <header class="stack"><bdi dir="ltr">{{ $ticket->reference }}</bdi><h1 class="text-title" dir="auto">{{ $ticket->subject }}</h1><p>{{ __('support::messages.state_'.$ticket->state) }} · {{ __('support::messages.category_'.$ticket->category) }}</p></header>
    @include('support::thread')
    @if(in_array($ticket->state, ['open', 'waiting_customer', 'resolved'], true))
        <p class="text-meta">{{ __('support::messages.'.($ticket->state === 'resolved' ? 'reopen_help' : 'reply_help')) }}</p>
        <form method="POST" action="{{ route('support.reply', $ticket) }}" class="stack">
            @csrf
            <input type="hidden" name="version" value="{{ $ticket->version }}">
            <input type="hidden" name="operation_key" value="{{ old('operation_key', $key) }}">
            <input type="hidden" name="action" value="{{ $ticket->state === 'resolved' ? 'reopened' : 'customer' }}">
            <div class="field"><label for="support-reply" class="field__label">{{ __('support::messages.body') }}</label><textarea id="support-reply" class="input" name="body" dir="auto" rows="4" maxlength="4000" required>{{ old('body') }}</textarea>@error('body')<p class="field__error" role="alert">{{ $message }}</p>@enderror</div>
            @foreach(['version', 'ticket', 'operation_key', 'action'] as $field) @error($field)<p class="field__error" role="alert">{{ $message }}</p>@enderror @endforeach
            <button class="button button--primary" type="submit">{{ __('support::messages.'.($ticket->state === 'resolved' ? 'reopen' : 'send')) }}</button>
        </form>
        <details class="stack"><summary>{{ __('support::messages.action_closed') }}</summary><form method="POST" action="{{ route('support.reply', $ticket) }}" class="stack">
            @csrf
            <input type="hidden" name="version" value="{{ $ticket->version }}"><input type="hidden" name="operation_key" value="{{ \Illuminate\Support\Str::uuid() }}"><input type="hidden" name="action" value="closed">
            <label class="checkbox"><input type="checkbox" name="confirmed" value="1" required><span>{{ __('support::messages.close_confirm') }}</span></label>
            @error('confirmed')<p class="field__error" role="alert">{{ $message }}</p>@enderror
            <button type="submit" class="button button--secondary">{{ __('support::messages.action_closed') }}</button>
        </form></details>
    @else
        <p role="status">{{ __('support::messages.closed_help') }}</p>
    @endif
    <a class="button button--secondary" href="{{ route('support.index') }}">{{ __('support::messages.back') }}</a>
</section>
@endsection
