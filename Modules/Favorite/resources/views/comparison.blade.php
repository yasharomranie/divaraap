@extends('site::layouts.app')
@section('title', __('favorite::comparison.title'))
@section('content')
<div class="shell shell--wide page comparison-page"><div class="stack stack--loose">
    <h1 class="title-page">{{ __('favorite::comparison.title') }}</h1>
    <p class="text-muted">{{ __('favorite::comparison.hint') }}</p>
    <a class="button button--ghost" href="{{ route('price-guide.index') }}">{{ __('listing::prices.title') }}</a>
    <div class="row row--wrap">
        <a class="button button--secondary" href="{{ route('listings.index', ['category' => $listings->first()?->category_id]) }}">{{ __('favorite::comparison.more') }}</a>
        @if($listings->isNotEmpty())
            <form method="POST" action="{{ route('comparison.clear') }}">@csrf @method('DELETE')<button class="button button--ghost" type="submit">{{ __('favorite::comparison.clear') }}</button></form>
        @endif
    </div>
    <div class="row row--wrap"><a class="button button--secondary" href="{{ route('dossiers.create') }}">{{ __('favorite::dossier.create') }}</a>@auth<a class="button button--ghost" href="{{ route('dossiers.index') }}">{{ __('favorite::dossier.title') }}</a>@endauth</div>
    @if($listings->isEmpty())
        <p>{{ __('favorite::comparison.empty') }}</p>
    @else
        @if($listings->count() === 1)<p>{{ __('favorite::comparison.one') }}</p>@endif
        <form method="GET" action="{{ route('comparison.index') }}" class="row row--wrap">
            <label><input type="checkbox" name="differences" value="1" @checked($differences) @disabled($listings->count() < 2)> {{ __('favorite::comparison.differences') }}</label>
            <button class="button button--secondary button--small" type="submit">{{ __('favorite::comparison.apply') }}</button>
        </form>
        @include('favorite::dossiers.table')
        @if($differences && $rows === [])<p role="status">{{ __('favorite::comparison.same') }}</p>@endif
    @endif
</div></div>
@endsection
