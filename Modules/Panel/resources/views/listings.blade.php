@extends('panel::layouts.panel', ['panelSection' => 'listings'])

@section('title', __('panel::messages.my_listings'))

@php
    $statusTabs = [
        'all' => __('panel::messages.all'),
        'scheduled' => __('listing::schedule.scheduled'),
        'paused' => __('listing::availability.paused'),
        'sold' => __('panel::messages.sold'),
        'expired' => __('panel::messages.expired'),
    ];
@endphp

@section('panel_content')
@if($errors->any())<div class="alert alert--critical" role="alert">@foreach($errors->all() as $error)<p>{{ $error }}</p>@endforeach</div>@endif
<header class="panel-head">
    <div class="panel-head__text">
        <h1 class="title-page">{{ __('panel::messages.my_listings') }}</h1>
        <p class="text-muted">{{ trans_choice('site::messages.results_count', $counts['all'], ['count' => $counts['all']]) }}</p>
    </div>
    <a href="{{ route('panel.listings.create') }}" class="button button--primary">
        <x-ui.icon name="plus"/>
        <span>{{ __('panel::messages.new_listing') }}</span>
    </a>
</header>

<form method="GET" action="{{ route('panel.listings.index') }}" class="row row--wrap">
    <input type="hidden" name="status" value="{{ $status }}">
    <span class="input-affix" style="flex:1 1 240px">
        <x-ui.icon name="search" class="input-affix__icon"/>
        <label class="visually-hidden" for="panel-search">{{ __('panel::messages.search_listings') }}</label>
        <input id="panel-search" type="search" name="search" value="{{ $search }}" class="input" placeholder="{{ __('panel::messages.search_listings') }}">
    </span>
    <button type="submit" class="button button--secondary">{{ __('site::messages.search') }}</button>
</form>

<nav class="chip-row">
    @foreach($statusTabs as $key => $label)
        <a href="{{ route('panel.listings.index', array_filter(['status' => $key === 'all' ? null : $key, 'search' => $search ?: null])) }}"
           class="pill {{ $status === $key ? 'is-active' : '' }}">
            {{ $label }}
            <span>{{ $counts[$key] ?? $counts['all'] }}</span>
        </a>
    @endforeach
</nav>

@if($listings->isNotEmpty())
    <form id="bulk-listings-form" class="card card__body stack stack--tight" method="post" action="{{ route('panel.listings.bulk.preview') }}" data-listing-bulk>
        @csrf
        <h2 class="title-section">{{ __('listing::bulk.title') }}</h2>
        <p class="field__hint" id="bulk-hint">{{ __('listing::bulk.hint') }}</p>
        <div class="row row--wrap">
            <button class="button button--ghost" type="button" data-bulk-all hidden>{{ __('listing::bulk.all') }}</button>
            <button class="button button--ghost" type="button" data-bulk-clear hidden>{{ __('listing::bulk.clear') }}</button>
            <span role="status" aria-live="polite" aria-atomic="true" data-bulk-status hidden>{{ __('listing::bulk.count') }} <strong data-bulk-count>0</strong></span>
        </div>
        <label class="field__label" for="bulk-action">{{ __('listing::bulk.action') }}</label>
        <select class="select" id="bulk-action" name="action" required aria-describedby="bulk-hint">
            @foreach(['pause', 'resume', 'sold', 'delete'] as $bulkAction)
                <option value="{{ $bulkAction }}">{{ __('listing::bulk.'.$bulkAction) }}</option>
            @endforeach
        </select>
        <button class="button button--secondary" type="submit" data-bulk-preview>{{ __('listing::bulk.preview') }}</button>
    </form>
    <section class="card">
        <div class="data-list">
            @foreach($listings as $listing)
                @php $meta = $listing->panelStatusMeta(); @endphp
                <article class="data-row" data-listing-id="{{ $listing->id }}">
                    <div class="data-row__media">
                        @if($listing->panelPrimaryImageUrl())
                            <img src="{{ $listing->panelPrimaryImageUrl() }}" alt="" loading="lazy">
                        @else
                            <span class="listing-card__placeholder"><x-ui.icon name="image"/></span>
                        @endif
                    </div>

                    <div class="data-row__main">
                        <label class="listing-bulk-choice">
                            <input type="checkbox" name="ids[]" form="bulk-listings-form" value="{{ $listing->id }}" data-bulk-choice aria-label="{{ __('listing::bulk.select', ['title' => $listing->title]) }}">
                            <span>{{ __('listing::bulk.select_short') }}</span>
                        </label>
                        <p class="data-row__title text-clamp-1">{{ $listing->getAttribute('title') }}</p>
                        <div class="data-row__meta">
                            <span class="text-price">{{ $listing->panelPriceLabel() }}</span>
                            <span class="badge">{{ $meta['label'] }}</span>
                            <span class="data-row__metric"><x-ui.icon name="eye"/>{{ (int) $listing->getAttribute('view_count') }}</span>
                            <span class="data-row__metric"><x-ui.icon name="heart"/>{{ (int) $listing->getAttribute('favorited_by_users_count') }}</span>
                            <span class="data-row__metric"><x-ui.icon name="map-pin"/>{{ $listing->panelLocationLabel() }}</span>
                            <span class="data-row__metric"><x-ui.icon name="clock"/>{{ $listing->panelExpirySummary() }}</span>
                        </div>
                    </div>

                    <div class="data-row__actions">
                        <a href="{{ route('listings.show', $listing) }}" class="button button--ghost button--small">{{ in_array($listing->statusValue(), ['paused', 'scheduled'], true) ? __('listing::availability.preview') : __('panel::messages.view') }}</a>
                        <a href="{{ route('panel.listings.improve', $listing) }}" class="button button--ghost button--small">{{ __('listing::improvement.title') }}</a>
                        <a href="{{ route('panel.listings.performance', $listing) }}" class="button button--ghost button--small">{{ __('listing::performance.title') }}</a>
                        <a href="{{ route('listings.location', $listing) }}" class="button button--ghost button--small">{{ __('listing::map.location') }}</a>
                        <a href="{{ route('panel.listings.edit', $listing) }}" class="button button--secondary button--small">{{ __('panel::messages.edit') }}</a>

                        <a class="button button--ghost button--small" href="{{ route('panel.listings.duplicate', $listing) }}">{{ __('listing::duplicate.title') }}</a>
                        <a class="button button--ghost button--small" href="{{ route('panel.listings.schedule', $listing) }}">{{ __('listing::schedule.title') }}</a>
                        @include('panel::partials.listing-availability')

                        @if($listing->statusValue() !== 'sold')
                            <form method="POST" action="{{ route('panel.listings.mark-sold', $listing) }}">
                                @csrf
                                <button type="submit" class="button button--ghost button--small">{{ __('panel::messages.mark_sold') }}</button>
                            </form>
                        @endif

                        @if($listing->statusValue() === 'expired')
                            <form method="POST" action="{{ route('panel.listings.republish', $listing) }}">
                                @csrf
                                <button type="submit" class="button button--ghost button--small">{{ __('panel::messages.republish') }}</button>
                            </form>
                        @endif

                        <form method="POST" action="{{ route('panel.listings.destroy', $listing) }}" data-confirm="{{ __('panel::messages.confirm_delete') }}">
                            @csrf
                            <button type="submit" class="button button--critical button--small">{{ __('panel::messages.delete') }}</button>
                        </form>
                    </div>
                </article>
            @endforeach
        </div>
    </section>

    {{ $listings->links('components.pagination') }}
@else
    <x-ui.empty-state icon="tag" :title="__('panel::messages.no_listings')" :text="__('panel::messages.no_listings_hint')">
        <a href="{{ route('panel.listings.create') }}" class="button button--primary">{{ __('panel::messages.new_listing') }}</a>
    </x-ui.empty-state>
@endif
@endsection
