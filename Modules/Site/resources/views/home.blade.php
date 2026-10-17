@extends('site::layouts.app')

@section('title', __('site::messages.hero_title'))
@section('description', __('site::messages.hero_lead'))

@section('content')
@php
    $slides = \Modules\Site\App\Support\HomeSlideDefaults::forDisplay($generalSettings['home_slides'] ?? []);
    $showcaseListings = $recentListings->filter(fn ($listing) => filled($listing->primaryImageUrl('card')))->take(3)->values();
@endphp
<h1 class="visually-hidden">{{ $slides[0]['title'] }}</h1>
<div class="shell shell--wide home-workspace">
    <aside class="home-sidebar" aria-label="{{ __('site::messages.categories') }}">
        <div class="home-sidebar__head"><x-ui.icon name="grid"/><h2 class="title-card">{{ __('site::messages.categories') }}</h2></div>
        <nav class="home-sidebar__categories">
            <a href="{{ route('listings.index') }}" class="home-sidebar__all"><x-ui.icon name="sparkle"/>{{ __('site::home.browse') }}<x-ui.icon name="chevron-left"/></a>
            @foreach($categories as $category)
                <a href="{{ route('listings.index', ['category' => $category->getKey()]) }}">
                    <span class="home-sidebar__icon">@if($category->iconUrl())<img src="{{ $category->iconUrl() }}" alt="">@else<x-ui.icon name="grid"/>@endif</span>
                    <span>{{ $category->displayName() }}</span><x-ui.icon name="chevron-left"/>
                </a>
            @endforeach
        </nav>
        <a href="{{ auth()->check() ? route('panel.listings.create') : route('login') }}" class="home-sidebar__sell"><x-ui.icon name="plus"/><strong>{{ __('site::home.sell') }}</strong><span>{{ __('site::home.sell_text') }}</span></a>
    </aside>
    <div class="home-workspace__main">
<section class="hero marketplace-hero" data-home-showcase aria-label="{{ __('site::home.highlights') }}">
    <div class="home-showcase__frame">
        <div class="home-search-strip">
                <form action="{{ route('listings.index') }}" method="GET" class="hero__search" role="search" data-location-search>
                    <label class="visually-hidden" for="hero-search">{{ __('site::home.search_hint') }}</label>
                    <div class="hero__search-row">
                        <span class="input-affix"><x-ui.icon name="search" class="input-affix__icon"/><input id="hero-search" type="search" name="search" class="input" placeholder="{{ __('site::home.search_hint') }}"></span>
                        <button type="submit" class="button button--accent">{{ __('site::messages.search') }}<x-ui.icon name="arrow-left"/></button>
                    </div>
                </form>
        </div>
        <details class="home-showcase__details" data-home-details>
            <summary class="home-showcase__summary">
                <span><span class="text-eyebrow">{{ __('site::home.highlights') }}</span><strong class="title-card" dir="auto">{{ $slides[0]['title'] }}</strong><span class="home-showcase__summary-link">{{ __('site::home.open_showcase') }}<x-ui.icon name="chevron-down"/></span></span>
                @if($previewImage = (! str_starts_with($slides[0]['image_path'], 'images/home-slides/') || $showcaseListings->isEmpty()) ? \Modules\Site\App\Support\LocalMedia::url($slides[0]['image_path']) : $showcaseListings->first()->primaryImageUrl('card'))<img src="{{ $previewImage }}" alt="" width="120" height="100">@endif
            </summary>
        <div class="marketplace-hero__layout">
            <div class="home-showcase__slides" aria-live="polite" aria-atomic="true">
                @foreach($slides as $index => $slide)
                    <div id="home-slide-{{ $index }}" class="home-showcase__slide" data-home-slide @if($index > 0) hidden @endif>
                        <div class="hero__inner">
                            <p class="marketplace-hero__eyebrow"><span aria-hidden="true"></span>{{ $slide['badge'] }}</p>
                            <h2 class="title-hero hero__title" dir="auto">{{ $slide['title'] }}</h2>
                            <p class="text-lead hero__lead" dir="auto">{{ $slide['subtitle'] }}</p>
                            <div class="home-showcase__actions">
                                <a href="{{ $index === 1 ? route('categories.index') : route('listings.index') }}" class="button button--primary">{{ $slide['primary_button_text'] }}<x-ui.icon name="arrow-left"/></a>
                                <a href="{{ auth()->check() ? route('panel.listings.create') : route('login') }}" class="home-showcase__sell">{{ $slide['secondary_button_text'] }}<x-ui.icon name="plus"/></a>
                            </div>
                        </div>
                        <div class="home-showcase__visual">
                            @if(str_starts_with($slide['image_path'], 'images/home-slides/') && $showcaseListings->isNotEmpty())
                                <div class="home-collage" aria-label="{{ __('site::home.fresh_finds') }}">
                                    @foreach($showcaseListings as $pick)
                                        <a class="home-collage__card" href="{{ route('listings.show', $pick) }}">
                                            <img src="{{ $pick->primaryImageUrl('card') }}" alt="{{ $pick->getAttribute('title') }}" width="280" height="300" @if($index > 0) loading="lazy" @endif>
                                            <span dir="auto">{{ $pick->getAttribute('title') }}</span>
                                            <strong><bdi>{{ $pick->panelPriceLabel() }}</bdi></strong>
                                        </a>
                                    @endforeach
                                </div>
                            @elseif($image = \Modules\Site\App\Support\LocalMedia::url($slide['image_path']))
                                <img src="{{ $image }}" alt="" width="800" height="500" @if($index === 0) fetchpriority="high" @else loading="lazy" @endif>
                            @else
                                <x-ui.icon name="grid"/>
                            @endif
                            <span class="home-showcase__stamp"><x-ui.icon name="inbox"/>{{ __('site::mobile.direct') }}</span>
                        </div>
                    </div>
                @endforeach
            </div>
            <div class="home-showcase__toolbar">
                @if(count($slides) > 1)
                    <div class="home-showcase__controls {{ count($slides) > 3 ? 'home-showcase__controls--many' : '' }}" data-home-controls hidden>
                        <button type="button" class="icon-button" data-home-previous aria-label="{{ __('site::home.previous') }}"><x-ui.icon name="chevron-right"/></button>
                        <div class="home-showcase__positions">
                            @foreach($slides as $index => $slide)
                                <button type="button" data-home-position="{{ $index }}" aria-controls="home-slide-{{ $index }}" aria-current="{{ $index === 0 ? 'true' : 'false' }}" aria-label="{{ __('site::home.slide', ['number' => $index + 1, 'total' => count($slides)]) }}"><span></span></button>
                            @endforeach
                        </div>
                        <button type="button" class="icon-button" data-home-next aria-label="{{ __('site::home.next') }}"><x-ui.icon name="chevron-left"/></button>
                    </div>
                @endif
            </div>
        </div>
        </details>
        <div class="home-market-pulse">
            <p><x-ui.icon name="sparkle"/>{{ __('site::mobile.hero_note') }}</p>
            <div class="hero__stats">
                <span class="hero__stat"><strong>{{ \App\Support\PersianLocale::number($listingCount) }}</strong> {{ __('site::messages.active_listings') }}</span>
                <span class="hero__stat"><strong>{{ \App\Support\PersianLocale::number($categoryCount) }}</strong> {{ __('site::messages.categories') }}</span>
            </div>
        </div>
    </div>
</section>

<div class="home-feed page">
    <div class="stack stack--section">
        <nav class="home-feed-navigation" aria-label="{{ __('site::home.explore_market') }}">
            <a href="#latest" class="is-current"><x-ui.icon name="clock"/>{{ __('site::messages.recent_listings') }}</a>
            @if($featuredListings->isNotEmpty())<a href="#featured"><x-ui.icon name="sparkle"/>{{ __('site::messages.featured') }}</a>@endif
            <a href="{{ route('listings.index') }}"><x-ui.icon name="sliders"/>{{ __('site::home.filter_listings') }}</a>
        </nav>

        @if($categories->isNotEmpty())
        <section class="section home-category-section">
            <div class="section__head">
                <div><p class="text-eyebrow">{{ __('site::home.categories_hint') }}</p><h2 class="title-section">{{ __('site::messages.popular_categories') }}</h2></div>
                <a href="{{ route('categories.index') }}" class="text-link">{{ __('site::messages.explore_all') }}</a>
            </div>

            <div class="grid grid--categories home-categories">
                @foreach($categories->take(12) as $category)
                    <a href="{{ route('listings.index', ['category' => $category->getKey()]) }}" class="category-card">
                        <span class="category-card__icon">
                            @if($category->iconUrl())
                                <img src="{{ $category->iconUrl() }}" alt="" loading="lazy">
                            @else
                                <x-ui.icon name="grid"/>
                            @endif
                        </span>
                        <span class="category-card__name">{{ $category->displayName() }}</span>
                    </a>
                @endforeach
            </div>
        </section>
        @endif

        @if($featuredListings->isNotEmpty())
        <section class="section home-featured-section" id="featured">
            <div class="section__head">
                <div><p class="text-eyebrow">{{ __('site::home.featured_hint') }}</p><h2 class="title-section">{{ __('site::messages.featured') }}</h2></div>
                <a href="{{ route('listings.index', ['sort' => 'relevance']) }}" class="text-link">{{ __('site::messages.view_all') }}</a>
            </div>

            <div class="grid grid--listings">
                @foreach($featuredListings as $listing)
                    <x-ui.listing-card :listing="$listing" :favorited="in_array((int) $listing->getKey(), $favoriteListingIds, true)"/>
                @endforeach
            </div>
        </section>
        @endif

        <section class="section home-recent-section" id="latest">
            <div class="section__head">
                <div><p class="text-eyebrow">{{ __('site::home.recent_hint') }}</p><h2 class="title-section">{{ __('site::messages.recent_listings') }}</h2></div>
                <a href="{{ route('listings.index', ['sort' => 'newest']) }}" class="text-link">{{ __('site::messages.view_all') }}</a>
            </div>

            @if($recentListings->isNotEmpty())
                <div class="grid grid--listings">
                    @foreach($recentListings as $listing)
                        <x-ui.listing-card :listing="$listing" :favorited="in_array((int) $listing->getKey(), $favoriteListingIds, true)"/>
                    @endforeach
                </div>
            @else
                <x-ui.empty-state icon="tag" :title="__('site::messages.no_listings')" :text="__('site::messages.no_listings_hint')"/>
            @endif
        </section>

        <section class="promo-banner home-sell-banner">
            <span class="home-sell-banner__icon" aria-hidden="true"><x-ui.icon name="plus"/></span>
            <p class="text-eyebrow">{{ __('site::home.sell_hint') }}</p>
            <h2 class="promo-banner__title">{{ __('site::messages.sell_something') }}</h2>
            <p class="promo-banner__text">{{ __('site::home.sell_text') }}</p>
            <div class="row row--wrap">
                <a href="{{ auth()->check() ? route('panel.listings.create') : route('register') }}" class="button button--secondary">
                    {{ __('site::messages.post_listing_cta') }}
                </a>
                <a href="{{ route('promotions.plans') }}" class="button button--ghost promo-banner__link">
                    {{ __('promotion::messages.plans') }}
                </a>
            </div>
        </section>
    </div>
</div>
    </div>
</div>
@endsection
