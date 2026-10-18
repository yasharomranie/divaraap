@extends('panel::layouts.panel', ['panelSection' => 'listings'])
@section('title', __('listing::map.location'))
@section('panel_content')
<div class="stack stack--loose listing-map-editor" data-listing-location data-loading="{{ __('listing::map.loading') }}" data-failed="{{ __('listing::map.editor_failed') }}" data-ready="{{ __('listing::map.pick_ready') }}" data-zoom-in="{{ __('listing::map.zoom_in') }}" data-zoom-out="{{ __('listing::map.zoom_out') }}">
    <h1 class="title-page">{{ __('listing::map.location') }}</h1>
    <h2 class="title-card" dir="auto">{{ $listing->title }}</h2>
    <p>{{ __('listing::map.pick_hint') }}</p>
    <a class="button button--ghost" href="{{ route('panel.listings.edit', $listing) }}">{{ __('panel::messages.edit') }}</a>
    <script type="application/json" data-location-point>@json($point)</script>
    <div class="listing-map-canvas" data-location-canvas role="region" aria-label="{{ __('listing::map.region') }}"></div>
    <p role="status" data-location-status></p>
    <noscript><p>{{ __('listing::map.editor_failed') }}</p></noscript>
    <form method="POST" action="{{ route('listings.location.save', $listing) }}" class="stack stack--tight">
        @csrf
        <label for="map-latitude">{{ __('listing::map.latitude') }}</label>
        <input class="input" id="map-latitude" name="latitude" type="number" step="any" min="-85.05112878" max="85.05112878" required value="{{ old('latitude', $point['lat'] ?? '') }}" dir="ltr">
        @error('latitude')<p role="alert">{{ $message }}</p>@enderror
        <label for="map-longitude">{{ __('listing::map.longitude') }}</label>
        <input class="input" id="map-longitude" name="longitude" type="number" step="any" min="-180" max="180" required value="{{ old('longitude', $point['lng'] ?? '') }}" dir="ltr">
        @error('longitude')<p role="alert">{{ $message }}</p>@enderror
        <button class="button button--primary" type="submit">{{ __('listing::map.save_location') }}</button>
    </form>
    @if($point !== null)<form method="POST" action="{{ route('listings.location.remove', $listing) }}">@csrf @method('DELETE')<button class="button button--ghost" type="submit">{{ __('listing::map.remove_location') }}</button></form>@endif
</div>
@endsection
