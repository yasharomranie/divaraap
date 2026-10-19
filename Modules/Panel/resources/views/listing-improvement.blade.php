@extends('panel::layouts.panel', ['panelSection' => 'listings'])
@section('title', __('listing::improvement.title'))
@section('panel_content')
<div class="listing-guide stack stack--loose">
    <header class="stack stack--tight">
        <a class="button button--ghost" href="{{ route('panel.listings.index') }}">{{ __('listing::improvement.back') }}</a>
        <h1 class="title-page">{{ __('listing::improvement.title') }}</h1>
        <h2 class="title-card" dir="auto">{{ $listing->title }}</h2>
        <p>{{ __('listing::improvement.hint') }}</p>
        <p>{{ __('listing::improvement.status') }}: {{ $listing->statusLabel() }}</p>
    </header>
    <section class="listing-guide-summary stack stack--tight" aria-labelledby="guide-progress">
        <h2 class="title-card" id="guide-progress" data-guide-progress>{{ __('listing::improvement.progress', ['completed' => \App\Support\PersianLocale::number($guide['completed']), 'total' => \App\Support\PersianLocale::number($guide['total'])]) }}</h2>
        <p class="text-muted">{{ __('listing::improvement.notice') }}</p>
        <p class="text-muted">{{ __('listing::improvement.saved_only') }}</p>
    </section>
    @if($guide['completed'] === $guide['total'])<p role="status">{{ __('listing::improvement.all_done') }}</p>@endif
    <ul class="listing-guide__checks">
        @foreach($guide['items'] as $item)
            <li class="listing-guide__check" data-guide-check="{{ $item['key'] }}" data-complete="{{ $item['complete'] ? 'true' : 'false' }}">
                <div class="stack stack--tight">
                    <p class="listing-guide__state">{{ __('listing::improvement.'.($item['complete'] ? 'done' : 'todo')) }}</p>
                    <h3 class="title-card">{{ __('listing::improvement.'.$item['key'].'_label') }}</h3>
                    <p class="text-muted">{{ __('listing::improvement.'.$item['key'].'_hint') }}</p>
                </div>
                @unless($item['complete'])
                    <a class="button button--secondary" href="{{ route('panel.listings.edit', $listing) }}#{{ $item['anchor'] }}" aria-label="{{ __('listing::improvement.fix') }}: {{ __('listing::improvement.'.$item['key'].'_label') }}">{{ __('listing::improvement.fix') }}</a>
                @endunless
            </li>
        @endforeach
    </ul>
    <p class="text-muted">{{ __('listing::improvement.privacy') }}</p>
    <p class="text-muted">{{ __('listing::improvement.moderation') }}</p>
    <div class="row row--wrap">
        <a class="button button--secondary" href="{{ route('panel.listings.edit', $listing) }}#listing-text-suggestions">{{ __('listing::suggestions.title') }}</a>
        <a class="button button--primary" href="{{ route('panel.listings.edit', $listing) }}">{{ __('listing::improvement.edit') }}</a>
        <a class="button button--ghost" href="{{ route('panel.listings.performance', $listing) }}">{{ __('listing::performance.title') }}</a>
    </div>
</div>
@endsection
