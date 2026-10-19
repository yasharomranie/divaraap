@extends('panel::layouts.panel', ['panelSection' => 'listings'])
@section('title', __('listing::performance.title'))
@section('panel_content')
<div class="listing-performance stack stack--loose">
    <header class="stack stack--tight">
        <a href="{{ route('panel.listings.index') }}" class="button button--ghost">{{ __('listing::performance.back') }}</a>
        <h1 class="title-page">{{ __('listing::performance.title') }}</h1>
        <h2 class="title-card" dir="auto">{{ $listing->title }}</h2>
        <p class="text-muted">{{ __('listing::performance.hint') }}</p>
        <p>{{ __('listing::performance.status') }}: {{ $listing->statusLabel() }}</p>
    </header>
    <form method="GET" action="{{ route('panel.listings.performance', $listing) }}" class="listing-performance__filter">
        <div class="field">
            <label class="field__label" for="performance-days">{{ __('listing::performance.period') }}</label>
            <select class="select" id="performance-days" name="days">
                @foreach([7, 30, 90] as $period)<option value="{{ $period }}" @selected($days === $period)>{{ __('listing::performance.days', ['days' => \App\Support\PersianLocale::number($period)]) }}</option>@endforeach
            </select>
        </div>
        <button class="button button--secondary" type="submit">{{ __('listing::performance.apply') }}</button>
    </form>
    <p><time datetime="{{ $start->toDateString() }}">{{ \App\Support\PersianLocale::date($start) }}</time> — <time datetime="{{ $end->toDateString() }}">{{ \App\Support\PersianLocale::date($end) }}</time></p>
    @unless($telemetryEnabled)<p role="status">{{ __('listing::performance.disabled') }}</p>@endunless
    <dl class="listing-performance__metrics">
        @foreach($metrics as $key => $value)
            <div class="listing-performance__metric" data-performance-metric="{{ $key }}"><dt>{{ __('listing::performance.'.$key) }}</dt><dd>{{ \App\Support\PersianLocale::number($value) }}</dd></div>
        @endforeach
    </dl>
    <p class="text-muted">{{ __('listing::performance.measurement') }}</p>
    <p class="text-muted">{{ __('listing::performance.actions_hint') }}</p>
    <section class="listing-performance__snapshot stack stack--tight">
        <h2 class="title-card">{{ __('listing::performance.favorites') }}: <span data-performance-favorites>{{ \App\Support\PersianLocale::number($favorites) }}</span></h2>
        <p class="text-muted">{{ __('listing::performance.saved_hint') }}</p>
        <p>{{ __('listing::performance.lifetime', ['count' => \App\Support\PersianLocale::number((int) $listing->view_count)]) }}</p>
        <p class="text-muted">{{ __('listing::performance.legacy_hint') }}</p>
    </section>
    <section class="stack stack--tight">
        <h2 class="title-section">{{ __('listing::performance.daily') }}</h2>
        <p class="text-muted">{{ __('listing::performance.coverage') }}</p>
        @if($report['views'] === 0 && $report['contacts'] === 0)<p role="status">{{ __('listing::performance.no_activity') }}</p>@endif
        <div class="listing-performance__daily" tabindex="0" role="region" aria-label="{{ __('listing::performance.daily') }}">
            <table>
                <caption class="visually-hidden">{{ __('listing::performance.daily') }}</caption>
                <thead><tr><th scope="col">{{ __('listing::performance.date') }}</th><th scope="col">{{ __('listing::performance.views') }}</th><th scope="col">{{ __('listing::performance.contacts') }}</th></tr></thead>
                <tbody>
                    @foreach(array_reverse($report['days']) as $row)
                        <tr><th scope="row"><time datetime="{{ $row['date']->toDateString() }}">{{ \App\Support\PersianLocale::date($row['date']) }}</time></th><td>{{ $row['view'] === null ? '—' : \App\Support\PersianLocale::number($row['view']) }}</td><td>{{ $row['contact'] === null ? '—' : \App\Support\PersianLocale::number($row['contact']) }}</td></tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </section>
    <div class="row row--wrap">
        <a class="button button--secondary" href="{{ route('panel.listings.improve', $listing) }}">{{ __('listing::improvement.title') }}</a>
        <a class="button button--primary" href="{{ route('panel.listings.edit', $listing) }}">{{ __('listing::performance.edit') }}</a>
        <a class="button button--ghost" href="{{ route('listings.show', $listing) }}">{{ __('listing::performance.view_listing') }}</a>
    </div>
</div>
@endsection
