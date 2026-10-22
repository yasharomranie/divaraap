@extends('panel::layouts.panel', ['panelSection' => $panelKey])
@section('title', $record->title)
@section('panel_content')
<div class="wanted stack stack--loose" data-wanted-owner="{{ $record->id }}">
    <h1 class="title-page" dir="auto">{{ $record->title }}</h1>
    <article class="card stack"><p><span class="badge">{{ $record->stateLabel() }}</span></p>@include('listing::wanted.summary')<p class="wanted-description" dir="auto">{{ $record->description }}</p>
    @if($record->moderation_note)<p>{{ __($copy.'.review_note') }}: <span dir="auto">{{ $record->moderation_note }}</span></p>@endif
    @if(in_array($record->state, ['pending', 'open', 'rejected'], true))
        <div class="wanted-actions">
            @if($record->expires_at->isFuture())<a class="button button--secondary" href="{{ route($routePrefix.'.edit', $record) }}">{{ __($copy.'.edit') }}</a>@endif
            @foreach($record->state === 'open' && $record->expires_at->isFuture() ? ['fulfilled', 'closed'] : ['closed'] as $state)
                <form method="post" action="{{ route($routePrefix.'.close', $record) }}">@csrf<input type="hidden" name="revision" value="{{ $record->revision }}"><input type="hidden" name="state" value="{{ $state }}"><button class="button button--ghost" type="submit">{{ __($copy.'.'.($state === 'fulfilled' ? 'fulfill' : 'close')) }}</button></form>
            @endforeach
        </div>
    @endif
    <p class="field__hint">{{ __($copy.'.closed_hint') }}</p></article>
    @include('listing::wanted.errors')
    <section class="stack"><h2>{{ __($copy.'.responses') }}</h2><p class="text-muted">{{ __($copy.'.revision_hint') }}</p>
    @forelse($responses as $response)
        @php($item = $cards[$response->id] ?? null)
        <article class="card stack" data-wanted-response="{{ $response->id }}">
            @if($item)<h3 dir="auto"><a href="{{ route('listings.show', $item['id']) }}">{{ $item['title'] }}</a></h3><p>{{ $item['price'] }}</p><form method="post" action="{{ route('conversations.start', $item['id']) }}">@csrf<button class="button button--primary" type="submit">{{ __($copy.'.contact') }}</button></form>@else<p>{{ __($copy.'.unavailable') }}</p>@endif
        </article>
    @empty<p>{{ __($copy.'.no_responses') }}</p>@endforelse
    {{ $responses->links() }}</section>
    <a href="{{ route($routePrefix.'.mine') }}">{{ __($copy.'.back') }}</a>
</div>
@endsection
