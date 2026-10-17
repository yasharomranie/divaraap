@extends('site::layouts.app')

@section('title', __('site::home.new_title').' '.__('site::home.new_title_end'))
@section('description', __('site::messages.hero_lead'))

@section('content')
@php
    $slides = \Modules\Site\App\Support\HomeSlideDefaults::forDisplay($generalSettings['home_slides'] ?? []);
    $spotlight = $recentListings->first(fn ($listing) => filled($listing->primaryImageUrl('card')));
@endphp
<div class="shell shell--wide home-canvas">
    <header class="home-intro">
        <div class="home-intro__copy">
            <p class="home-kicker"><span></span>{{ __('site::home.new_eyebrow') }}</p>
            <h1 class="title-hero">{{ __('site::home.new_title') }}<span>{{ __('site::home.new_title_end') }}</span></h1>
            <p class="text-lead">{{ __('site::home.new_lead') }}</p>
        </div>
        <div class="home-intro__search">
            <form action="{{ route('listings.index') }}" method="GET" class="home-search" role="search" data-location-search>
                <label for="hero-search">{{ __('site::home.search_hint') }}</label>
                <div class="home-search__field">
                    <x-ui.icon name="search"/>
                    <input id="hero-search" type="search" name="search" class="input" placeholder="{{ __('site::messages.search_placeholder') }}">
                    <button type="submit" aria-label="{{ __('site::messages.search') }}"><x-ui.icon name="arrow-left"/></button>
                </div>
            </form>
            <div class="home-intro__links">
                <a href="{{ route('listings.index') }}">{{ __('site::home.browse') }}<x-ui.icon name="arrow-left"/></a>
                <span>{{ \App\Support\PersianLocale::number($listingCount) }} {{ __('site::messages.active_listings') }}</span>
            </div>
        </div>
    </header>

    @if($categories->isNotEmpty())
        <nav class="home-directory" aria-label="{{ __('site::messages.categories') }}">
            @foreach($categories->take(8) as $category)
                <a href="{{ route('listings.index', ['category' => $category->getKey()]) }}">
                    <span class="home-directory__icon">@if($category->iconUrl())<img src="{{ $category->iconUrl() }}" alt="" loading="lazy">@else<x-ui.icon name="grid"/>@endif</span>
                    <span class="category-card__name">{{ $category->displayName() }}</span>
                    <x-ui.icon name="arrow-left"/>
                </a>
            @endforeach
            <a href="{{ route('categories.index') }}" class="home-directory__all"><span>{{ __('site::messages.explore_all') }}</span><x-ui.icon name="grid"/></a>
        </nav>
    @endif

    <section class="home-editorial" data-home-showcase aria-label="{{ __('site::home.highlights') }}">
        <div class="home-editorial__slides" aria-live="polite" aria-atomic="true">
            @foreach($slides as $index => $slide)
                @php
                    $customImage = ! str_starts_with($slide['image_path'], 'images/home-slides/');
                    $editorialImage = $customImage ? \Modules\Site\App\Support\LocalMedia::url($slide['image_path']) : $spotlight?->primaryImageUrl('card');
                @endphp
                <div id="home-slide-{{ $index }}" class="home-editorial__slide" data-home-slide @if($index > 0) hidden @endif>
                    <div class="home-editorial__story">
                        <span class="home-section-number" aria-hidden="true">{{ \App\Support\PersianLocale::number($index + 1) }} / {{ \App\Support\PersianLocale::number(count($slides)) }}</span>
                        <p class="text-eyebrow">{{ $slide['badge'] }}</p>
                        <h2 class="title-section" dir="auto">{{ $slide['title'] }}</h2>
                        <p class="text-body" dir="auto">{{ $slide['subtitle'] }}</p>
                        <div class="home-editorial__actions">
                            <a href="{{ route('listings.index') }}" class="home-arrow-link">{{ $slide['primary_button_text'] }}<x-ui.icon name="arrow-left"/></a>
                            <a href="{{ auth()->check() ? route('panel.listings.create') : route('login') }}">{{ $slide['secondary_button_text'] }}</a>
                        </div>
                    </div>
                    <div class="home-editorial__picture">
                        @if($editorialImage)
                            <img src="{{ $editorialImage }}" alt="{{ $customImage ? $slide['title'] : $spotlight?->getAttribute('title') }}" width="900" height="700" @if($index === 0) fetchpriority="high" @else loading="lazy" @endif>
                        @else
                            <div class="home-editorial__placeholder"><x-ui.icon name="tag"/><span>{{ __('site::home.new_discover') }}</span></div>
                        @endif
                        @if(! $customImage && $spotlight)
                            <a class="home-editorial__caption" href="{{ route('listings.show', $spotlight) }}"><span><span class="text-meta">{{ __('site::mobile.fresh') }}</span><strong dir="auto">{{ $spotlight->getAttribute('title') }}</strong></span><bdi>{{ $spotlight->panelPriceLabel() }}</bdi><x-ui.icon name="arrow-left"/></a>
                        @endif
                    </div>
                </div>
            @endforeach
        </div>
        @if(count($slides) > 1)
            <div class="home-editorial__controls" data-home-controls hidden>
                <button type="button" data-home-previous aria-label="{{ __('site::home.previous') }}"><x-ui.icon name="arrow-right"/></button>
                <div class="home-editorial__positions">
                    @foreach($slides as $index => $slide)
                        <button type="button" data-home-position="{{ $index }}" aria-controls="home-slide-{{ $index }}" aria-current="{{ $index === 0 ? 'true' : 'false' }}" aria-label="{{ __('site::home.slide', ['number' => $index + 1, 'total' => count($slides)]) }}"><span></span></button>
                    @endforeach
                </div>
                <button type="button" data-home-next aria-label="{{ __('site::home.next') }}"><x-ui.icon name="arrow-left"/></button>
            </div>
        @endif
    </section>

    @if($featuredListings->isNotEmpty())
        <section class="home-collection home-collection--featured" id="featured">
            <div class="home-section-head"><div><p class="text-eyebrow">{{ __('site::home.new_spotlight') }}</p><h2 class="title-section">{{ __('site::messages.featured') }}</h2></div><a href="{{ route('listings.index', ['sort' => 'relevance']) }}" class="home-arrow-link">{{ __('site::messages.view_all') }}<x-ui.icon name="arrow-left"/></a></div>
            <div class="home-product-grid">
                @foreach($featuredListings->take(4) as $listing)
                    <x-ui.listing-card :listing="$listing" :favorited="in_array((int) $listing->getKey(), $favoriteListingIds, true)"/>
                @endforeach
            </div>
        </section>
    @endif

    <section class="home-collection home-collection--recent" id="latest">
        <div class="home-section-head"><div><p class="text-eyebrow">{{ __('site::home.new_discover') }}</p><h2 class="title-section">{{ __('site::messages.recent_listings') }}</h2></div><a href="{{ route('listings.index', ['sort' => 'newest']) }}" class="home-arrow-link">{{ __('site::home.browse') }}<x-ui.icon name="arrow-left"/></a></div>
        @if($recentListings->isNotEmpty())
            <div class="home-product-grid">
                @foreach($recentListings as $listing)
                    <x-ui.listing-card :listing="$listing" :favorited="in_array((int) $listing->getKey(), $favoriteListingIds, true)"/>
                @endforeach
            </div>
        @else
            <x-ui.empty-state icon="tag" :title="__('site::messages.no_listings')" :text="__('site::messages.no_listings_hint')"/>
        @endif
        <a href="{{ route('listings.index') }}" class="home-more">{{ __('site::home.new_more') }}<x-ui.icon name="arrow-left"/></a>
    </section>

    <section class="home-sell">
        <div><p class="text-eyebrow">{{ __('site::home.sell_hint') }}</p><h2 class="title-page">{{ __('site::home.new_sell') }}</h2><p class="text-body">{{ __('site::home.sell_text') }}</p></div>
        <a href="{{ auth()->check() ? route('panel.listings.create') : route('login') }}" class="home-sell__action"><span>{{ __('site::messages.post_listing_cta') }}</span><x-ui.icon name="plus"/></a>
    </section>
</div>
@endsection
