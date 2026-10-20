@extends('panel::layouts.panel', ['panelSection' => 'listings'])
@section('title', __('listing::schedule.title'))
@section('panel_content')
<div class="listing-schedule stack stack--loose">
    <header class="stack stack--tight">
        <a class="button button--ghost" href="{{ route('panel.listings.index') }}">{{ __('listing::schedule.back') }}</a>
        <h1 class="title-page">{{ __('listing::schedule.title') }}</h1>
        <p dir="auto">{{ $listing->title }}</p>
        <p>{{ __('listing::schedule.hint') }}</p>
    </header>
    @if($errors->any())<div class="alert alert--critical" role="alert">@foreach($errors->all() as $error)<p>{{ $error }}</p>@endforeach</div>@endif
    <section class="card stack">
        <strong>{{ $listing->statusLabel() }}</strong>
        <p>{{ __('listing::schedule.current') }}: {{ $listing->publish_at ? \App\Support\PersianLocale::date($listing->publish_at->copy()->timezone('Asia/Tehran'), true) : __('listing::schedule.none') }}</p>
        @if($listing->statusValue() === 'pending')<p>{{ __('listing::schedule.pending') }}</p>@endif
        <p class="field__hint">{{ __('listing::schedule.expiry') }}</p>
    </section>
    @if(in_array($listing->statusValue(), ['pending', 'active', 'paused', 'scheduled'], true))
        <form class="card stack" action="{{ route('panel.listings.schedule.save', $listing) }}" method="post" @if($listing->statusValue() === 'active') data-confirm="{{ __('listing::schedule.confirm') }}" @endif>
            @csrf
            <input type="hidden" name="version" value="{{ $listing->scheduleVersion() }}">
            <label for="listing-publish-at" class="field__label">{{ __('listing::schedule.date') }}</label>
            <input class="input" id="listing-publish-at" name="publish_at" type="datetime-local" dir="ltr" value="{{ $listing->publish_at?->copy()->timezone('Asia/Tehran')->format('Y-m-d\TH:i') }}" min="{{ now()->timezone('Asia/Tehran')->addMinute()->format('Y-m-d\TH:i') }}" max="{{ now()->timezone('Asia/Tehran')->addDays(90)->format('Y-m-d\TH:i') }}" required aria-describedby="schedule-range">
            <p class="field__hint" id="schedule-range">{{ __('listing::schedule.range') }}</p>
            <button class="button button--primary" type="submit">{{ __('listing::schedule.save') }}</button>
        </form>
        @if($listing->publish_at)
            <form action="{{ route('panel.listings.schedule.cancel', $listing) }}" method="post">
                @csrf
                @method('DELETE')
                <input type="hidden" name="version" value="{{ $listing->scheduleVersion() }}">
                <button class="button button--secondary" type="submit">{{ __('listing::schedule.cancel') }}</button>
                <p class="field__hint">{{ __('listing::schedule.cancel_hint') }}</p>
            </form>
        @endif
    @else
        <p>{{ __('listing::schedule.invalid_state') }}</p>
    @endif
    <p class="field__hint">{{ __('listing::schedule.behavior') }}</p>
</div>
@endsection
