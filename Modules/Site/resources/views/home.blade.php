@extends('site::layouts.app')

@section('title', __('site::messages.hero_title'))
@section('description', __('site::messages.hero_lead'))

@section('content')
<section class="hero marketplace-hero">
    <div class="shell shell--wide">
        <div class="marketplace-hero__layout">
            <div class="hero__inner">
                <p class="marketplace-hero__eyebrow"><span aria-hidden="true"></span>{{ __('site::mobile.eyebrow') }}</p>
                <h1 class="title-hero hero__title">{{ __('site::messages.hero_title') }}</h1>
                <p class="text-lead hero__lead">{{ __('site::messages.hero_lead') }}</p>
                <form action="{{ route('listings.index') }}" method="GET" class="hero__search" role="search" data-location-search>
                    <div class="hero__search-row">
                        <span class="input-affix">
                            <x-ui.icon name="search" class="input-affix__icon"/>
                            <label class="visually-hidden" for="hero-search">{{ __('site::messages.search') }}</label>
                            <input id="hero-search" type="search" name="search" class="input" placeholder="{{ __('site::messages.search_placeholder') }}">
                        </span>
                        <button type="submit" class="button button--accent button--large">{{ __('site::messages.search') }}<x-ui.icon name="arrow-left"/></button>
                    </div>
                </form>
                <div class="hero__stats">
                    <span class="hero__stat"><strong>{{ \App\Support\PersianLocale::number($listingCount) }}</strong> {{ __('site::messages.active_listings') }}</span>
                    <span class="hero__stat"><strong>{{ \App\Support\PersianLocale::number($categoryCount) }}</strong> {{ __('site::messages.categories') }}</span>
                    <span class="hero__stat"><strong>{{ \App\Support\PersianLocale::number($userCount) }}</strong> {{ __('site::messages.members') }}</span>
                </div>
            </div>
            <div class="marketplace-hero__discovery">
                <div class="marketplace-hero__discovery-head"><span class="marketplace-hero__symbol"><x-ui.icon name="sparkle"/></span><span>{{ __('site::mobile.discover') }}</span><x-ui.icon name="arrow-left"/></div>
                @if($recentListings->isNotEmpty())
                    @php($heroListing = $recentListings->first())
                    <a class="discovery-card" href="{{ route('listings.show', $heroListing) }}">
                        <div class="discovery-card__image">
                            @if($heroListing->primaryImageUrl('card'))
                                <img src="{{ $heroListing->primaryImageUrl('card') }}" alt="" fetchpriority="high">
                            @else
                                <x-ui.icon name="tag"/>
                            @endif
                            <span class="discovery-card__label"><x-ui.icon name="clock"/>{{ __('site::mobile.fresh') }}</span>
                        </div>
                        <div class="discovery-card__body"><span dir="auto">{{ $heroListing->getAttribute('title') }}</span><strong><bdi>{{ $heroListing->panelPriceLabel() }}</bdi></strong><span class="discovery-card__action"><x-ui.icon name="arrow-left"/></span></div>
                    </a>
                @else
                    <div class="discovery-card discovery-card--empty"><x-ui.icon name="tag"/><p>{{ __('site::mobile.trust_title') }}</p></div>
                @endif
                <p class="marketplace-hero__note"><x-ui.icon name="users"/>{{ __('site::mobile.direct') }}</p>
            </div>
        </div>
    </div>
</section>

<div class="shell shell--wide page">
    <div class="stack stack--section">
        @if($categories->isNotEmpty())
        <section class="section">
            <div class="section__head">
                <h2 class="title-section">{{ __('site::messages.popular_categories') }}</h2>
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
        <section class="section">
            <div class="section__head">
                <h2 class="title-section">{{ __('site::messages.featured') }}</h2>
                <a href="{{ route('listings.index', ['sort' => 'relevance']) }}" class="text-link">{{ __('site::messages.view_all') }}</a>
            </div>

            <div class="grid grid--listings">
                @foreach($featuredListings as $listing)
                    <x-ui.listing-card :listing="$listing" :favorited="in_array((int) $listing->getKey(), $favoriteListingIds, true)"/>
                @endforeach
            </div>
        </section>
        @endif

        <section class="section">
            <div class="section__head">
                <h2 class="title-section">{{ __('site::messages.recent_listings') }}</h2>
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

        <section class="promo-banner">
            <h2 class="promo-banner__title">{{ __('site::messages.sell_something') }}</h2>
            <p class="promo-banner__text">{{ __('site::messages.hero_lead') }}</p>
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
@endsection
