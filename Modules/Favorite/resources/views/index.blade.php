@extends('site::layouts.app')

@section('title', __('favorite::messages.favorites'))

@php
    $tabs = [
        'listings' => __('favorite::messages.saved_listings'),
        'searches' => __('favorite::messages.saved_searches'),
        'sellers' => __('favorite::messages.followed_sellers'),
    ];
@endphp

@section('content')
<div class="shell shell--wide page buyer-favorites">
    <div class="stack stack--loose">
        <header class="stack stack--tight">
            <h1 class="title-page">{{ __('favorite::messages.favorites') }}</h1>
        </header>

        <nav class="row row--wrap" aria-label="{{ __('favorite::collections.tools') }}">
            <a class="button button--ghost button--small" href="{{ route('history.index') }}">{{ __('favorite::history.title') }}</a>
            <a class="button button--ghost button--small" href="{{ route('comparison.index') }}">{{ __('favorite::comparison.title') }}</a>
            @unless($requiresLogin)
                <a class="button button--ghost button--small" href="{{ route('favorites.alerts') }}">{{ __('favorite::alerts.title') }}</a>
                <a class="button button--ghost button--small" href="{{ route('collections.index') }}">{{ __('favorite::collections.title') }}</a>
            @endunless
        </nav>
        @if($requiresLogin)
            <x-ui.empty-state icon="heart" :title="__('favorite::messages.no_favorites')" :text="__('favorite::messages.no_favorites_hint')">
                <a href="{{ route('login') }}" class="button button--primary">{{ __('site::messages.login') }}</a>
            </x-ui.empty-state>
        @else
            <nav class="chip-row">
                @foreach($tabs as $key => $label)
                    <a href="{{ route('favorites.index', ['tab' => $key]) }}" class="pill {{ $activeTab === $key ? 'is-active' : '' }}">{{ $label }}</a>
                @endforeach
            </nav>

            @if($activeTab === 'listings')
                <form method="GET" action="{{ route('favorites.index') }}" class="row row--wrap" data-filter-form>
                    <input type="hidden" name="tab" value="listings">
                    <label class="visually-hidden" for="fav-collection">{{ __('favorite::collections.choose') }}</label>
                    <select id="fav-collection" name="collection" class="select" style="max-width:230px" data-filter-auto>
                        <option value="">{{ __('favorite::collections.all') }}</option>
                        <option value="none" @selected($collectionFilter === 'none')>{{ __('favorite::collections.none') }}</option>
                        @foreach($collections as $id => $name)<option value="{{ $id }}" @selected($collectionFilter === (int) $id)>{{ $name }}</option>@endforeach
                    </select>
                    <label class="visually-hidden" for="fav-status">{{ __('panel::messages.status') }}</label>
                    <select id="fav-status" name="status" class="select" style="max-width:190px" data-filter-auto>
                        <option value="all" @selected($statusFilter === 'all')>{{ __('site::messages.all') }}</option>
                        <option value="active" @selected($statusFilter === 'active')>{{ __('panel::messages.active') }}</option>
                    </select>

                    <label class="visually-hidden" for="fav-category">{{ __('site::messages.category') }}</label>
                    <select id="fav-category" name="category" class="select" style="max-width:230px" data-filter-auto>
                        <option value="">{{ __('site::messages.all_categories') }}</option>
                        @foreach($categories as $id => $name)
                            <option value="{{ $id }}" @selected($selectedCategoryId === (int) $id)>{{ \Modules\Category\Models\Category::localizedName($name) }}</option>
                        @endforeach
                    </select>
                    <button type="submit" class="button button--secondary button--small">{{ __('favorite::collections.apply') }}</button>
                </form>

                @if($favoriteListings->isNotEmpty())
                    <div class="grid grid--listings">
                        @foreach($favoriteListings as $listing)
                            <div class="stack stack--tight"><x-ui.listing-card :listing="$listing" :favorited="true"/>
                            @include('favorite::partials.alert-toggle', ['kind' => 'price', 'sourceId' => $listing->id, 'enabled' => $alertPreferences['price:'.$listing->id] ?? false, 'subject' => $listing->title])
                            @include('favorite::partials.collection-move', ['listing' => $listing])</div>
                        @endforeach
                    </div>
                    {{ $favoriteListings->links('components.pagination') }}
                @else
                    @if($collectionFilter !== null)
                    <x-ui.empty-state icon="heart" :title="__('favorite::collections.empty_group')" :text="__('favorite::collections.move_hint')">
                        <a href="{{ route('favorites.index') }}" class="button button--secondary">{{ __('favorite::collections.favorites') }}</a>
                    </x-ui.empty-state>
                    @else
                    <x-ui.empty-state icon="heart" :title="__('favorite::messages.no_favorites')" :text="__('favorite::messages.no_favorites_hint')">
                        <a href="{{ route('listings.index') }}" class="button button--secondary">{{ __('site::messages.browse') }}</a>
                    </x-ui.empty-state>
                    @endif
                @endif
            @elseif($activeTab === 'searches')
                @if($favoriteSearches->isNotEmpty())
                    <div class="card">
                        <div class="data-list">
                            @foreach($favoriteSearches as $search)
                                <div class="data-row" style="grid-template-columns: minmax(0,1fr) auto">
                                    <div class="data-row__main">
                                        <p class="data-row__title">{{ $search->label ?: __('site::messages.all_listings') }}</p>
                                        <p class="data-row__meta">
                                            <time datetime="{{ $search->getAttribute('created_at')?->toIso8601String() }}">
                                                {{ $search->getAttribute('created_at')?->diffForHumans() }}
                                            </time>
                                        </p>
                                    </div>
                                    <div class="data-row__actions">
                                        @include('favorite::partials.alert-toggle', ['kind' => 'search', 'sourceId' => $search->id, 'enabled' => $alertPreferences['search:'.$search->id] ?? false, 'subject' => $search->label])
                                        <a href="{{ route('listings.index', $search->filters ?? []) }}" class="button button--secondary button--small">
                                            {{ __('favorite::messages.run_search') }}
                                        </a>
                                        <form method="POST" action="{{ route('favorites.searches.destroy', $search) }}">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="button button--ghost button--small">{{ __('favorite::messages.remove') }}</button>
                                        </form>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                    {{ $favoriteSearches->links('components.pagination') }}
                @else
                    <x-ui.empty-state icon="search" :title="__('favorite::messages.no_favorites')" :text="__('favorite::messages.no_favorites_hint')"/>
                @endif
            @else
                @if($favoriteSellers->isNotEmpty())
                    <div class="card">
                        <div class="data-list">
                            @foreach($favoriteSellers as $seller)
                                <div class="data-row" style="grid-template-columns: auto minmax(0,1fr) auto">
                                    <div class="data-row__main">
                                        <p class="data-row__title">{{ $seller->getAttribute('name') }}</p>
                                        <p class="data-row__meta">{{ __('user::messages.seller_since', ['date' => \App\Support\PersianLocale::date($seller->getAttribute('created_at'))]) }}</p>
                                    </div>
                                    <div class="data-row__actions">
                                        @include('favorite::partials.alert-toggle', ['kind' => 'seller', 'sourceId' => $seller->id, 'enabled' => $alertPreferences['seller:'.$seller->id] ?? false, 'subject' => $seller->name])
                                        <a href="{{ route('sellers.show', $seller) }}" class="button button--secondary button--small">{{ __('site::messages.view_profile') }}</a>
                                        <button type="button" class="button button--ghost button--small" data-favorite-toggle="{{ route('favorites.sellers.toggle', $seller) }}">
                                            {{ __('favorite::messages.remove') }}
                                        </button>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                    {{ $favoriteSellers->links('components.pagination') }}
                @else
                    <x-ui.empty-state icon="users" :title="__('favorite::messages.no_favorites')" :text="__('favorite::messages.no_favorites_hint')"/>
                @endif
            @endif
        @endif
    </div>
</div>
@endsection
