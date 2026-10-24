@extends('site::layouts.app')
@section('title', __('favorite::sharing.shared_title'))
@section('content')
<div class="shell shell--wide wanted page stack stack--loose">
    <h1 class="title-page">{{ __('favorite::sharing.shared_title') }}</h1>
    <p>{{ __('favorite::sharing.public_hint') }}</p>
    <p>{{ __('favorite::sharing.expires') }} <time datetime="{{ $expires->toIso8601String() }}">{{ \App\Support\PersianLocale::date($expires) }}</time></p>
    @if($missing)<p class="alert">{{ __('favorite::sharing.missing') }}</p>@endif
    <div class="wanted-grid">
    @foreach($items as $listing)
        <article class="card stack wanted-card" data-shared-choice="{{ $listing->id }}">
            @if($image = $listing->primaryImageUrl('card'))<img class="shortlist-image" src="{{ $image }}" alt="" width="360" height="240" loading="lazy">@endif
            <h2 dir="auto"><a href="{{ route('listings.show', $listing) }}">{{ $listing->title }}</a></h2>
            <p><bdi>{{ $listing->panelPriceLabel() }}</bdi></p><p dir="auto">{{ $listing->comparisonPlaceLabel() }}</p>
            <a class="button button--secondary" href="{{ route('listings.show', $listing) }}">{{ __('favorite::sharing.open_listing') }}</a>
        </article>
    @endforeach
    </div>{{ $items->links() }}
</div>
@endsection
