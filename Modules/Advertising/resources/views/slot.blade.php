@php
    $banners = app(\Modules\Advertising\Support\BannerRenderer::class)->at($placementKey, $position ?? 0);
@endphp
@foreach($banners as $device => $banner)
    <aside class="advertising-banner advertising-banner--{{ $device }}" aria-label="{{ __('advertising::admin.sponsored') }}" data-banner="{{ $banner->id }}" data-placement="{{ $placementKey }}">
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
