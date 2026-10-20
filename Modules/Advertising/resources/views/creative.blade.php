@if($banner->creativeEligible())
<aside class="advertising-banner advertising-banner--{{ $device }}" aria-label="{{ __('advertising::admin.sponsored') }}" data-banner="{{ $banner->id }}" data-placement="{{ $placementKey }}" @if($trackingToken) data-behavior="advertising-metrics" data-event-url="{{ route('advertising.events') }}" data-event-token="{{ $trackingToken }}" @endif>
    <span class="advertising-banner__label">{{ __('advertising::admin.sponsored') }}</span>
    @if(! ($isPreview ?? false) && $banner->destination())
        <a href="{{ $banner->destination() }}" @if($banner->new_tab) target="_blank" @endif rel="sponsored noopener noreferrer">
    @endif
    @if($banner->creative_format === 'collection')
        @include('advertising::listing-collection')
    @elseif($banner->creative_format === 'service')
        @include('advertising::service-card')
    @elseif($banner->creative_format === 'shop')
        @include('advertising::shop-card')
    @else
    <picture>
        @if($banner->mobile_image)<source media="(max-width: 1023px)" srcset="{{ $banner->imageUrl(true) }}">@endif
        <img src="{{ $banner->imageUrl() }}" alt="{{ $banner->alt }}" width="1200" height="400" loading="lazy" decoding="async">
    </picture>
    @endif
    @if(! ($isPreview ?? false) && $banner->destination())</a>@endif
</aside>

@endif
