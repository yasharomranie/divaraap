<div class="advertising-service" data-ad-service="{{ $banner->service_listing_id }}">
    <picture class="advertising-service__media">
        @if($banner->mobile_image)<source media="(max-width: 1023px)" srcset="{{ $banner->imageUrl(true) }}">@endif
        <img class="advertising-service__cover" src="{{ $banner->imageUrl() }}" alt="{{ $banner->alt }}" width="400" height="400" loading="lazy" decoding="async">
    </picture>
    <div class="advertising-service__body">
        <p class="text-meta" dir="auto">{{ $banner->title }}</p>
        <h2 class="advertising-service__name" dir="auto">{{ $banner->advertisedService()['title'] }}</h2>
        <p class="text-price" dir="auto">{{ $banner->advertisedService()['price'] }}</p>
        <span class="advertising-service__action">{{ __('advertising::service.action') }} <x-ui.icon name="arrow-left"/></span>
        <p class="text-meta">{{ __('advertising::service.note') }}</p>
    </div>
</div>
