<div class="advertising-collection" data-ad-collection="{{ $banner->id }}">
    <header class="advertising-collection__head">
        <img class="advertising-collection__cover" src="{{ $banner->imageUrl() }}" alt="{{ $banner->alt }}" width="120" height="120" loading="lazy" decoding="async">
        <div><h2 dir="auto">{{ $banner->title }}</h2><p class="text-meta">{{ __('advertising::collection.count', ['count' => \App\Support\PersianLocale::digits(count($banner->advertisedListings()))]) }}</p></div>
    </header>
    <div class="advertising-collection__grid">
        @foreach($banner->advertisedListings() as $item)
            <article class="advertising-collection__item" data-ad-item="{{ $item['id'] }}">
                @if(!($isPreview ?? false))<a href="{{ route('listings.show', $item['id']) }}" data-ad-listing="{{ $item['id'] }}" @if($banner->new_tab) target="_blank" @endif rel="sponsored noopener noreferrer">@endif
                @if($item['image'])<img class="advertising-collection__photo" src="{{ $item['image'] }}" alt="" width="320" height="240" loading="lazy" decoding="async">@else<span class="advertising-collection__placeholder" aria-hidden="true"><x-ui.icon name="image"/></span>@endif
                <h3 class="text-clamp-2" dir="auto">{{ $item['title'] }}</h3>
                <p class="text-price" dir="auto">{{ $item['price'] }}</p>
                @if(!($isPreview ?? false))</a>@endif
            </article>
        @endforeach
    </div>
</div>
