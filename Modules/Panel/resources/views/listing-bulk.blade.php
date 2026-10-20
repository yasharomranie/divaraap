@extends('panel::layouts.panel', ['panelSection' => 'listings'])
@section('title', __('listing::bulk.review'))
@section('panel_content')
<header class="stack stack--tight">
    <a class="button button--ghost" href="{{ route('panel.listings.index') }}">{{ __('listing::bulk.back') }}</a>
    <h1 class="title-page">{{ __('listing::bulk.review') }}</h1>
    <h2 class="title-section">{{ __('listing::bulk.'.$action) }}</h2>
    <p>{{ __('listing::bulk.'.$action.'_hint') }}</p>
</header>
<section class="card">
    <div class="card__body stack stack--tight">
        <p>{{ __('listing::bulk.count') }} {{ \App\Support\PersianLocale::number($listings->count()) }}</p>
        <ul class="stack stack--tight">
            @foreach($listings as $listing)
                <li style="overflow-wrap:anywhere" dir="auto">{{ $listing->title }} — {{ $listing->statusLabel() }}</li>
            @endforeach
        </ul>
        <p class="field__hint">{{ __('listing::bulk.atomic') }}</p>
        <p class="field__hint">{{ __('listing::bulk.expires') }}</p>
    </div>
</section>
<form class="stack stack--tight" action="{{ route('panel.listings.bulk.execute') }}" method="post">
    @csrf
    <input type="hidden" name="ticket" value="{{ $ticket }}">
    <label class="listing-bulk-choice">
        <input type="checkbox" name="confirmed" value="1" required>
        <span>{{ __('listing::bulk.confirm') }}</span>
    </label>
    <button class="button {{ $action === 'delete' ? 'button--critical' : 'button--primary' }}" type="submit">{{ __('listing::bulk.execute') }}</button>
</form>
@endsection
