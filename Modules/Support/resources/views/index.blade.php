@extends('panel::layouts.panel', ['panelSection' => 'support'])
@section('title', __('support::messages.title'))
@section('panel_content')
<header class="panel-head"><h1 class="title-page">{{ __('support::messages.title') }}</h1><a class="button button--primary" href="{{ route('support.create') }}">{{ __('support::messages.new') }}</a></header>
<div class="stack support-ticket">
    @forelse($tickets as $ticket)
        <article class="card card__body stack">
            <a href="{{ route('support.show', $ticket) }}"><bdi dir="ltr">{{ $ticket->reference }}</bdi><h2 class="text-title" dir="auto">{{ $ticket->subject }}</h2></a>
            <p>{{ __('support::messages.state_'.$ticket->state) }} · {{ __('support::messages.category_'.$ticket->category) }}</p>
            <time datetime="{{ $ticket->last_activity_at->toIso8601String() }}">{{ $ticket->last_activity_at->timezone('Asia/Tehran')->format('Y/m/d H:i') }}</time>
        </article>
    @empty
        <p>{{ __('support::messages.empty') }}</p>
    @endforelse
</div>
{{ $tickets->links('components.pagination') }}
@endsection
