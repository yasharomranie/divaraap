<div class="advertising-shop" data-ad-shop="{{ $banner->shop_user_id }}">
    <picture class="advertising-shop__media">
    @if($banner->mobile_image)<source media="(max-width: 1023px)" srcset="{{ $banner->imageUrl(true) }}">@endif
    <img class="advertising-shop__cover" src="{{ $banner->imageUrl() }}" alt="{{ $banner->alt }}" width="1200" height="400" loading="lazy" decoding="async">
    </picture>
    <div class="advertising-shop__body">
        <p class="text-meta" dir="auto">{{ $banner->title }}</p>
        <h2 class="advertising-shop__name" dir="auto">{{ $banner->shop_snapshot['name'] }}</h2>
        @if($banner->shop_snapshot['description'])<p class="text-meta text-clamp-2" dir="auto">{{ $banner->shop_snapshot['description'] }}</p>@endif
        @if($banner->shop_snapshot['hours'])<p class="text-meta" dir="auto">{{ __('advertising::shop.hours') }}: {{ $banner->shop_snapshot['hours'] }}</p>@endif
        <span class="advertising-shop__action">{{ __('advertising::shop.visit') }}</span>
    </div>
</div>
