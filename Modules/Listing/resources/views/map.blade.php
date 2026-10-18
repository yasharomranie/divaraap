@extends('site::layouts.app')
@section('title', __('listing::map.title'))
@section('content')
<div class="shell shell--wide page listing-map-page stack stack--loose" data-listing-map data-zoom-in="{{ __('listing::map.zoom_in') }}" data-zoom-out="{{ __('listing::map.zoom_out') }}" data-loading="{{ __('listing::map.loading') }}" data-failed="{{ __('listing::map.failed') }}" data-ready="{{ __('listing::map.ready') }}" data-nearby="{{ __('listing::map.nearby') }}" data-group-hint="{{ __('listing::map.group_hint') }}">
    <h1 class="title-page">{{ __('listing::map.title') }}</h1>
    <p class="text-muted">{{ __('listing::map.hint') }}</p>
    <div class="row row--wrap">
        <a class="button button--secondary" href="{{ route('listings.index', request()->except(['page', 'west', 'east', 'south', 'north'])) }}">{{ __('listing::map.list') }}</a>
        <a class="button button--ghost" href="{{ route('listings.map', request()->except(['page', 'west', 'east', 'south', 'north'])) }}">{{ __('listing::map.reset') }}</a>
    </div>
    <details class="listing-map-filters"><summary>{{ __('listing::map.filters') }}</summary>
        <form method="GET" action="{{ route('listings.map') }}" class="listing-map-filter-grid">
            <label>{{ __('listing::map.search') }}<input class="input" name="search" value="{{ $search }}" maxlength="120"></label>
            <label>{{ __('listing::map.category') }}<select class="select" name="category"><option value="">{{ __('listing::map.all') }}</option>@foreach($categoryOptions as $id => $name)<option value="{{ $id }}" @selected((int) $categoryId === (int) $id)>{{ \Modules\Category\Models\Category::localizedName($name) }}</option>@endforeach</select></label>
            <label>{{ __('listing::map.country') }}<select class="select" name="country" data-dependent-source data-dependent-target="map-city" data-dependent-url="{{ route('locations.cities', ['country' => '__VALUE__']) }}"><option value="">{{ __('listing::map.all') }}</option>@foreach($countries as $country)<option value="{{ $country->id }}" @selected($countryId === (int) $country->id)>{{ $country->displayName() }}</option>@endforeach</select></label>
            <label>{{ __('listing::map.city') }}<select class="select" name="city" id="map-city" data-placeholder="{{ __('listing::map.all') }}"><option value="">{{ __('listing::map.all') }}</option>@foreach($cities as $city)<option value="{{ $city->id }}" @selected($cityId === (int) $city->id)>{{ $city->displayName() }}</option>@endforeach</select></label>
            <label>{{ __('listing::map.min_price') }}<input class="input" name="min_price" inputmode="numeric" value="{{ $minPriceInput }}"></label>
            <label>{{ __('listing::map.max_price') }}<input class="input" name="max_price" inputmode="numeric" value="{{ $maxPriceInput }}"></label>
            <label>{{ __('listing::map.date') }}<select class="select" name="date_filter">@foreach(['all', 'today', 'week', 'month'] as $value)<option value="{{ $value }}" @selected($dateFilter === $value)>{{ __('listing::map.'.$value) }}</option>@endforeach</select></label>
            <label>{{ __('listing::map.sort') }}<select class="select" name="sort">@foreach(['smart', 'newest', 'oldest', 'price_asc', 'price_desc'] as $value)<option value="{{ $value }}" @selected($sort === $value)>{{ __('site::messages.sort_'.($value === 'smart' ? 'relevance' : $value)) }}</option>@endforeach</select></label>
            @if($sellerUserId)<input type="hidden" name="user" value="{{ $sellerUserId }}">@endif
            @foreach($bounds ?? [] as $key => $value)<input type="hidden" name="{{ $key }}" value="{{ $value }}">@endforeach
            <button type="submit" class="button button--primary">{{ __('listing::map.apply') }}</button>
        </form>
    </details>
    @if(\App\Support\PersianSearch::terms($search) === null)<p role="alert">{{ __('listing::search.limit') }}</p>@endif
    <p role="status">{{ __('listing::map.shown', ['shown' => \App\Support\PersianLocale::number(count($mapPoints)), 'total' => \App\Support\PersianLocale::number($mapTotal)]) }}</p>
    <script type="application/json" data-map-points>@json($mapPoints)</script>
    <script type="application/json" data-map-bounds>@json($bounds)</script>
    <div class="listing-map-layout">
        <section class="listing-map-panel stack stack--tight">
            <div class="listing-map-canvas" data-map-canvas role="region" aria-label="{{ __('listing::map.region') }}"></div>
            <p class="text-muted" data-map-status role="status"></p>
            <noscript><p>{{ __('listing::map.failed') }}</p></noscript>
            <div class="row row--wrap"><a class="button button--primary" data-map-search-area href="{{ request()->fullUrl() }}" hidden>{{ __('listing::map.area') }}</a><button class="button button--secondary" type="button" data-map-retry hidden>{{ __('listing::map.retry') }}</button></div>
        </section>
        <section class="listing-map-results stack stack--tight" aria-label="{{ __('listing::map.results') }}">
            @forelse($listings as $listing)
                <article class="listing-map-result" data-map-result="{{ $listing->id }}">
                    <h2 class="title-card" dir="auto"><a href="{{ route('listings.show', $listing->id) }}">{{ $listing->title }}</a></h2>
                    <p><bdi>{{ $listing->panelPriceLabel() }}</bdi></p>
                    <p class="text-muted">{{ $listing->comparisonPlaceLabel() }}</p>
                    <button class="button button--ghost button--small" type="button" data-map-focus="{{ $listing->id }}" aria-label="{{ __('listing::map.focus') }}: {{ $listing->title }}" hidden>{{ __('listing::map.focus') }}</button>
                </article>
            @empty
                <p>{{ __('listing::map.empty') }}</p>
            @endforelse
            {{ $listings->links('components.pagination') }}
        </section>
    </div>
</div>
@endsection
