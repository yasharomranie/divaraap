@php
    $banners = app(\Modules\Advertising\Support\BannerRenderer::class)->at($placementKey, $position ?? 0, $advertisingContext ?? []);
@endphp
@foreach($banners as $device => $banner)
    @php($trackingToken = request()->hasSession() && app(\Modules\Admin\App\Settings\OperationsSettings::class)->telemetry_enabled ? \Modules\Advertising\Support\AdvertisingToken::issue($banner, $placementKey, $device, $advertisingContext ?? []) : null)
    <aside class="advertising-banner advertising-banner--{{ $device }}" aria-label="{{ __('advertising::admin.sponsored') }}" data-banner="{{ $banner->id }}" data-placement="{{ $placementKey }}" @if($trackingToken) data-behavior="advertising-metrics" data-event-url="{{ route('advertising.events') }}" data-event-token="{{ $trackingToken }}" @endif>
        <span class="advertising-banner__label">{{ __('advertising::admin.sponsored') }}</span>
        @if($banner->destination())
            <a href="{{ $banner->destination() }}" @if($banner->new_tab) target="_blank" @endif rel="sponsored noopener noreferrer">
        @endif
        <picture>
            @if($banner->mobile_image)<source media="(max-width: 1023px)" srcset="{{ $banner->imageUrl(true) }}">@endif
            <img src="{{ $banner->imageUrl() }}" alt="{{ $banner->alt }}" width="1200" height="400" loading="lazy" decoding="async">
        </picture>
        @if($banner->destination())</a>@endif
    </aside>
@endforeach
