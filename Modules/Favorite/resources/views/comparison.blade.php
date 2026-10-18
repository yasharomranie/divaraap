@extends('site::layouts.app')
@section('title', __('favorite::comparison.title'))
@section('content')
<div class="shell shell--wide page comparison-page"><div class="stack stack--loose">
    <h1 class="title-page">{{ __('favorite::comparison.title') }}</h1>
    <p class="text-muted">{{ __('favorite::comparison.hint') }}</p>
    <div class="row row--wrap">
        <a class="button button--secondary" href="{{ route('listings.index', ['category' => $listings->first()?->category_id]) }}">{{ __('favorite::comparison.more') }}</a>
        @if($listings->isNotEmpty())
            <form method="POST" action="{{ route('comparison.clear') }}">@csrf @method('DELETE')<button class="button button--ghost" type="submit">{{ __('favorite::comparison.clear') }}</button></form>
        @endif
    </div>
    @if($listings->isEmpty())
        <p>{{ __('favorite::comparison.empty') }}</p>
    @else
        @if($listings->count() === 1)<p>{{ __('favorite::comparison.one') }}</p>@endif
        <form method="GET" action="{{ route('comparison.index') }}" class="row row--wrap">
            <label><input type="checkbox" name="differences" value="1" @checked($differences) @disabled($listings->count() < 2)> {{ __('favorite::comparison.differences') }}</label>
            <button class="button button--secondary button--small" type="submit">{{ __('favorite::comparison.apply') }}</button>
        </form>
        <p id="comparison-scroll" class="text-muted">{{ __('favorite::comparison.scroll') }}</p>
        <div class="comparison-scroll" tabindex="0" role="region" aria-labelledby="comparison-scroll">
            <table class="comparison-table" style="min-width: {{ 110 + 170 * $listings->count() }}px">
                <caption class="visually-hidden">{{ __('favorite::comparison.title') }}</caption>
                <thead><tr><th scope="col">{{ __('favorite::comparison.specifications') }}</th>
                @foreach($listings as $listing)
                    <th scope="col">
                        @if($image = $listing->primaryImageUrl('card'))<img src="{{ $image }}" alt="{{ $listing->title }}" width="160" height="110" loading="lazy">@endif
                        <a href="{{ route('listings.show', $listing) }}" dir="auto">{{ $listing->title }}</a>
                        <form method="POST" action="{{ route('comparison.remove', $listing->id) }}">@csrf @method('DELETE')<button class="button button--ghost button--small" type="submit" aria-label="{{ __('favorite::comparison.remove') }}: {{ $listing->title }}">{{ __('favorite::comparison.remove') }}</button></form>
                    </th>
                @endforeach
                </tr></thead>
                <tbody>
                    @foreach($rows as $row)
                        <tr @class(['comparison-different' => $row['different']])><th scope="row">{{ $row['label'] }}</th>
                        @foreach($row['values'] as $value)<td dir="auto">{{ $value }}</td>@endforeach
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        @if($differences && $rows === [])<p role="status">{{ __('favorite::comparison.same') }}</p>@endif
    @endif
</div></div>
@endsection
