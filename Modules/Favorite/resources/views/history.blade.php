@extends('site::layouts.app')
@section('title', __('favorite::history.title'))
@section('content')
<div class="shell page history-page"><div class="stack stack--loose">
    <h1 class="title-page">{{ __('favorite::history.title') }}</h1>
    <p class="text-muted">{{ __('favorite::history.hint') }}</p>
    <div class="row row--wrap">
        <form method="POST" action="{{ route('history.configure') }}">@csrf<input type="hidden" name="enabled" value="{{ $enabled ? '0' : '1' }}"><button type="submit" class="button button--secondary">{{ __('favorite::history.'.($enabled ? 'pause' : 'resume')) }}</button></form>
        @if($listings->total())<form method="POST" action="{{ route('history.clear') }}">@csrf @method('DELETE')<button type="submit" class="button button--ghost">{{ __('favorite::history.clear') }}</button></form>@endif
    </div>
    @unless($enabled)<p role="status">{{ __('favorite::history.paused') }}</p>@endunless
    @forelse($listings as $listing)
        @php($at = \Illuminate\Support\Carbon::createFromTimestamp($entries[$listing->id], config('app.timezone')))
        <article class="history-entry">
            @if($image = $listing->primaryImageUrl('card'))<a href="{{ route('listings.show', $listing) }}" tabindex="-1" aria-hidden="true"><img src="{{ $image }}" alt="" width="72" height="72" loading="lazy"></a>@endif
            <div class="history-entry__body">
                <h2 class="title-card" dir="auto"><a href="{{ route('listings.show', $listing) }}">{{ $listing->title }}</a></h2>
                <p><bdi>{{ $listing->panelPriceLabel() }}</bdi></p>
                <p class="text-muted">{{ __('favorite::history.viewed') }}: <time datetime="{{ $at->toIso8601String() }}">{{ \App\Support\PersianLocale::date($at) }} · {{ \App\Support\PersianLocale::number($at->format('H:i')) }}</time></p>
                <form method="POST" action="{{ route('history.remove', $listing->id) }}">@csrf @method('DELETE')<button type="submit" class="button button--ghost button--small" aria-label="{{ __('favorite::history.remove') }}: {{ $listing->title }}">{{ __('favorite::history.remove') }}</button></form>
            </div>
        </article>
    @empty
        <p>{{ __('favorite::history.empty') }}</p>
        <a href="{{ route('listings.index') }}" class="button button--primary">{{ __('favorite::history.browse') }}</a>
    @endforelse
    {{ $listings->links('components.pagination') }}
</div></div>
@endsection
