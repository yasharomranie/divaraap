<div class="advertising-sponsor" data-ad-sponsor="{{ $banner->sponsored_category_id }}">
    <picture class="advertising-sponsor__media">
        @if($banner->mobile_image)<source media="(max-width: 1023px)" srcset="{{ $banner->imageUrl(true) }}">@endif
        <img class="advertising-sponsor__cover" src="{{ $banner->imageUrl() }}" alt="{{ $banner->alt }}" width="120" height="120" loading="lazy" decoding="async">
    </picture>
    <div class="advertising-sponsor__body">
        <p class="text-meta" dir="auto">{{ __('advertising::sponsor.supports', ['category' => $banner->sponsoredCategoryName()]) }}</p>
        <h2 dir="auto">{{ $banner->shop_snapshot['name'] }}</h2>
        <p class="text-meta" dir="auto">{{ $banner->title }}</p>
        <span class="advertising-sponsor__action">{{ __('advertising::shop.visit') }}</span>
        <p class="text-meta">{{ __('advertising::sponsor.disclosure') }}</p>
    </div>
</div>
