<div class="advertising-shop advertising-guide" data-ad-guide="{{ $banner->guide_page_id }}">
    <picture class="advertising-shop__media">
        @if($banner->mobile_image)<source media="(max-width: 1023px)" srcset="{{ $banner->imageUrl(true) }}">@endif
        <img class="advertising-shop__cover" src="{{ $banner->imageUrl() }}" alt="{{ $banner->alt }}" width="400" height="400" loading="lazy" decoding="async">
    </picture>
    <div class="advertising-shop__body">
        <p class="text-meta" dir="auto">{{ __('advertising::guide.label') }}</p>
        <h2 class="advertising-shop__name" dir="auto">{{ $banner->advertisedGuide()['title'] }}</h2>
        @if($banner->advertisedGuide()['excerpt'])<p class="text-meta" dir="auto">{{ $banner->advertisedGuide()['excerpt'] }}</p>@endif
        <span class="advertising-shop__action">{{ __('advertising::guide.read') }}</span>
    </div>
</div>
