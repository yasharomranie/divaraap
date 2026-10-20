@extends('panel::layouts.panel', ['panelSection' => 'listings'])
@section('title', __('listing::duplicate.title'))
@section('panel_content')
<div class="listing-duplicate stack stack--loose">
    <header class="stack stack--tight">
        <a class="button button--ghost" href="{{ route('panel.listings.index') }}">{{ __('listing::duplicate.back') }}</a>
        <h1 class="title-page">{{ __('listing::duplicate.title') }}</h1>
        <p class="text-muted">{{ __('listing::duplicate.hint') }}</p>
    </header>
    @if($errors->any())
        <div class="alert alert--critical" role="alert">@foreach($errors->all() as $error)<p>{{ $error }}</p>@endforeach</div>
    @endif
    @if($hasDraft)
        <section class="card stack">
            <p>{{ __('listing::duplicate.has_draft') }}</p>
            <a class="button button--primary" href="{{ route('panel.listings.create') }}">{{ __('listing::duplicate.continue') }}</a>
        </section>
    @else
        <section class="card stack" aria-labelledby="duplicate-review-heading">
            <h2 id="duplicate-review-heading" class="title-card">{{ __('listing::duplicate.review') }}</h2>
            <dl class="stack stack--tight">
                <dt>{{ __('panel::messages.title') }}</dt><dd dir="auto">{{ $payload['listingTitle'] }}</dd>
                <dt>{{ __('panel::messages.description') }}</dt><dd class="listing-duplicate__text" dir="auto" tabindex="0" aria-label="{{ __('panel::messages.description') }}">{{ $payload['description'] }}</dd>
                <dt>{{ __('panel::messages.price') }}</dt><dd dir="auto">{{ $payload['price'] !== '' ? \App\Support\Money::format($payload['price'], \Modules\Listing\Support\ListingPanelHelper::defaultCurrency()) : '—' }}</dd>
            </dl>
            <p class="field__hint">{{ __('listing::duplicate.limits') }}</p>
            <p class="field__hint">{{ __('listing::duplicate.price_hint') }}</p>
        </section>
        <form class="card stack" method="post" action="{{ route('panel.listings.duplicate.store', $listing) }}">
            @csrf
            <input type="hidden" name="source_version" value="{{ $sourceVersion }}">
            @if($photoCount > 0)
                <label class="listing-duplicate__option"><input name="photos" type="checkbox" value="1"> {{ __('listing::duplicate.photos', ['count' => $photoCount]) }}</label>
            @endif
            @if($listing->latitude !== null && $listing->longitude !== null)
                <label class="listing-duplicate__option"><input name="location" type="checkbox" value="1"> {{ __('listing::duplicate.location') }}</label>
            @endif
            <p class="field__hint">{{ __('listing::duplicate.media_hint') }}</p>
            <p>{{ __('listing::duplicate.privacy') }}</p>
            <button type="submit" class="button button--primary">{{ __('listing::duplicate.create') }}</button>
        </form>
    @endif
</div>
@endsection
