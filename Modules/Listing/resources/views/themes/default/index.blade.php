@extends('site::layouts.app')

@section('title', $selectedCategory?->displayName() ?? __('site::messages.all_listings'))
@section('description', __('site::messages.hero_lead'))

@php
    $sortOptions = [
        'smart' => __('site::messages.sort_relevance'),
        'newest' => __('site::messages.sort_newest'),
        'oldest' => __('site::messages.sort_oldest'),
        'price_asc' => __('site::messages.sort_price_asc').' ('.\App\Support\Money::currencyLabel('IRR').')',
        'price_desc' => __('site::messages.sort_price_desc').' ('.\App\Support\Money::currencyLabel('IRR').')',
    ];
    $dateOptions = [
        'all' => __('site::messages.any_time'),
        'today' => __('site::messages.today'),
        'week' => __('site::messages.this_week'),
        'month' => __('site::messages.this_month'),
    ];
@endphp

@section('content')
<div class="shell shell--wide page">
    <div class="stack stack--loose">
        <nav class="breadcrumb" aria-label="{{ __('site::messages.breadcrumb') }}">
            <a href="{{ route('home') }}">{{ __('site::messages.home') }}</a>
            <span class="breadcrumb__separator">/</span>
            <a href="{{ route('listings.index') }}">{{ __('site::messages.all_listings') }}</a>
            @if($selectedCategory)
                <span class="breadcrumb__separator">/</span>
                <span>{{ $selectedCategory->displayName() }}</span>
            @endif
        </nav>

        @if(\App\Support\PersianSearch::terms($search) === null)
            <p id="search-error" role="alert"><a class="search-error-desktop" href="#site-search-input">{{ __('listing::search.limit') }}</a><a class="search-error-mobile" href="#mobile-market-query">{{ __('listing::search.limit') }}</a></p>
        @endif
        <a class="button button--secondary button--small" href="{{ route('listings.map', request()->except(['page', 'west', 'east', 'south', 'north'])) }}">{{ __('listing::map.title') }}</a>
        <div class="panel-head">
            <div class="panel-head__text">
                <h1 class="title-page">{{ $selectedCategory?->displayName() ?? __('site::messages.all_listings') }}</h1>
                <p class="text-muted">{{ trans_choice('site::messages.results_count', $filteredListingsTotal, ['count' => \App\Support\PersianLocale::number($filteredListingsTotal)]) }}</p>
            </div>

            <a class="button button--ghost button--small" href="{{ route('history.index') }}">{{ __('favorite::history.title') }}</a>
            @auth
                <form method="POST" action="{{ route('favorites.searches.store') }}">
                    @csrf
                    <input type="hidden" name="search" value="{{ $search }}">
                    <input type="hidden" name="category" value="{{ $categoryId }}">
                    <input type="hidden" name="country" value="{{ $countryId }}">
                    <input type="hidden" name="city" value="{{ $cityId }}">
                    <input type="hidden" name="user" value="{{ $sellerUserId }}">
                    <input type="hidden" name="min_price" value="{{ $minPriceInput }}">
                    <input type="hidden" name="max_price" value="{{ $maxPriceInput }}">
                    <button type="submit" class="button button--secondary button--small" @disabled($isCurrentSearchSaved)>
                        <x-ui.icon name="heart"/>
                        <span>{{ $isCurrentSearchSaved ? __('site::messages.saved') : __('site::messages.save') }}</span>
                    </button>
                </form>
            @endauth
        </div>

        <div class="browse-layout">
            <aside class="drawer" data-filter-drawer aria-hidden="true">
                <button type="button" class="drawer__scrim" data-filter-drawer-close aria-label="{{ __('site::messages.close') }}"></button>
                <div class="drawer__panel drawer__panel--end" role="dialog" aria-modal="true" aria-label="{{ __('site::messages.filters') }}">
                    <div class="drawer__head">
                        <span class="drawer__title">{{ __('site::messages.filters') }}</span>
                        <button type="button" class="icon-button" data-filter-drawer-close aria-label="{{ __('site::messages.close') }}">
                            <x-ui.icon name="close"/>
                        </button>
                    </div>

                    <form method="GET" action="{{ route('listings.index') }}" class="drawer__body">
                        <input type="hidden" name="search" value="{{ $search }}">

                        <div class="filter-group">
                            <p class="filter-group__title">{{ __('site::messages.category') }}</p>
                            <div class="filter-group__options">
                                <label class="radio">
                                    <input type="radio" name="category" value="" @checked($categoryId === null)>
                                    <span>{{ __('site::messages.all_categories') }}</span>
                                </label>
                                @foreach($categories as $category)
                                    <label class="radio">
                                        <input type="radio" name="category" value="{{ $category->getKey() }}" @checked($categoryId === (int) $category->getKey())>
                                        <span>{{ $category->displayName() }}</span>
                                    </label>
                                @endforeach
                            </div>
                        </div>

                        <div class="filter-group">
                            <p class="filter-group__title">{{ __('site::messages.location') }}</p>
                            <div class="field">
                                <label class="field__label" for="filter-country">{{ __('site::messages.country') }}</label>
                                <select id="filter-country" name="country" class="select" data-dependent-source data-dependent-target="filter-city" data-dependent-url="{{ route('locations.cities', ['country' => '__VALUE__'], false) }}">
                                    <option value="">{{ __('site::messages.all_countries') }}</option>
                                    @foreach($countries as $country)
                                        <option value="{{ $country->getKey() }}" @selected($countryId === (int) $country->getKey())>{{ $country->displayName() }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="field">
                                <label class="field__label" for="filter-city">{{ __('site::messages.city') }}</label>
                                <select id="filter-city" name="city" class="select" data-placeholder="{{ __('site::messages.all_cities') }}" @disabled($cities->isEmpty())>
                                    <option value="">{{ __('site::messages.all_cities') }}</option>
                                    @foreach($cities as $city)
                                        <option value="{{ $city->getKey() }}" @selected($cityId === (int) $city->getKey())>{{ $city->displayName() }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>

                        <div class="filter-group">
                            <p class="filter-group__title">{{ __('site::messages.price_range') }} ({{ \App\Support\Money::currencyLabel('IRR') }})</p>
                            <div class="field__row field__row--two">
                                <div class="field">
                                    <label class="field__label" for="filter-min">{{ __('site::messages.min') }}</label>
                                    <input id="filter-min" type="text" inputmode="decimal" min="0" name="min_price" value="{{ $minPriceInput }}" class="input" placeholder="0">
                                </div>
                                <div class="field">
                                    <label class="field__label" for="filter-max">{{ __('site::messages.max') }}</label>
                                    <input id="filter-max" type="text" inputmode="decimal" min="0" name="max_price" value="{{ $maxPriceInput }}" class="input" placeholder="—">
                                </div>
                            </div>
                        </div>

                        <div class="filter-group">
                            <p class="filter-group__title">{{ __('site::messages.posted') }}</p>
                            <div class="filter-group__options">
                                @foreach($dateOptions as $value => $label)
                                    <label class="radio">
                                        <input type="radio" name="date_filter" value="{{ $value }}" @checked($dateFilter === $value)>
                                        <span>{{ $label }}</span>
                                    </label>
                                @endforeach
                            </div>
                        </div>

                        <div class="row">
                            <button type="submit" class="button button--primary button--block">{{ __('site::messages.apply') }}</button>
                            <a href="{{ route('listings.index') }}" class="button button--ghost">{{ __('site::messages.reset') }}</a>
                        </div>
                    </form>
                </div>
            </aside>
            <div class="stack stack--loose">
                <div class="result-bar">
                    <button type="button" class="button button--secondary button--small" data-filter-drawer-open>
                        <x-ui.icon name="sliders"/>
                        <span>{{ __('site::messages.filters') }}</span>
                    </button>

                    <div class="row">
                        <form method="GET" action="{{ route('listings.index') }}" data-filter-form>
                            @foreach(request()->except(['sort', 'page']) as $key => $value)
                                @if(is_scalar($value))
                                    <input type="hidden" name="{{ $key }}" value="{{ $value }}">
                                @endif
                            @endforeach
                            <label class="visually-hidden" for="sort-select">{{ __('site::messages.sort') }}</label>
                            <select id="sort-select" name="sort" class="select" data-filter-auto>
                                @foreach($sortOptions as $value => $label)
                                    <option value="{{ $value }}" @selected($sort === $value)>{{ $label }}</option>
                                @endforeach
                            </select>
                        </form>

                        <div class="button-group" data-view-toggle>
                            <button type="button" class="button-group__item" data-view-mode="grid" aria-pressed="true" aria-label="{{ __('site::messages.grid_view') }}">
                                <x-ui.icon name="grid" style="width:16px;height:16px"/>
                            </button>
                            <button type="button" class="button-group__item" data-view-mode="list" aria-pressed="false" aria-label="{{ __('site::messages.list_view') }}">
                                <x-ui.icon name="list" style="width:16px;height:16px"/>
                            </button>
                        </div>
                    </div>
                </div>

                @if($listings->isNotEmpty())
                    <div class="grid grid--listings" data-listing-collection data-view-mode="grid">
                        @foreach($listings as $listing)
                            <x-ui.listing-card :listing="$listing" :favorited="in_array((int) $listing->getKey(), $favoriteListingIds, true)"/>
                            @includeIf('advertising::slot', ['placementKey' => filled($search) ? 'search-feed' : ($selectedCategory ? 'category-feed' : 'listings-feed'), 'position' => ($listings->firstItem() - 1) + $loop->iteration, 'advertisingContext' => ['category_id' => $categoryId, 'city_id' => $cityId]])
                        @endforeach
                    </div>

                    {{ $listings->links('components.pagination') }}
                @else
                    <x-ui.empty-state icon="search" :title="__('site::messages.no_listings')" :text="__('site::messages.no_listings_hint')">
                        <a href="{{ route('listings.index') }}" class="button button--secondary button--small">{{ __('site::messages.reset') }}</a>
                    </x-ui.empty-state>
                @endif
            </div>

        </div>
    </div>
</div>
@endsection
