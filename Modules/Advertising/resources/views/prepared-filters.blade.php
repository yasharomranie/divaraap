<div class="advertising-filters" data-ad-filters="{{ $banner->id }}">
    <header class="advertising-filters__head">
        <picture>
            @if($banner->mobile_image)<source media="(max-width: 1023px)" srcset="{{ $banner->imageUrl(true) }}">@endif
            <img class="advertising-filters__cover" src="{{ $banner->imageUrl() }}" alt="{{ $banner->alt }}" width="120" height="120" loading="lazy" decoding="async">
        </picture>
        <div><h2 dir="auto">{{ $banner->title }}</h2><p class="text-meta">{{ __('advertising::filters.choose') }}</p></div>
    </header>
    <div class="advertising-filters__choices">
        @foreach($banner->preparedFilters() as $key => $choice)
            @if(!($isPreview ?? false))
                <a class="advertising-filters__choice" href="{{ $choice['url'] }}" data-ad-choice="{{ $key }}" @if($banner->new_tab) target="_blank" @endif rel="sponsored noopener noreferrer">
                    <strong dir="auto">{{ $choice['label'] }}</strong><span class="text-meta" dir="auto">{{ $choice['summary'] }}</span>
                </a>
            @else
                <div class="advertising-filters__choice"><strong dir="auto">{{ $choice['label'] }}</strong><span class="text-meta" dir="auto">{{ $choice['summary'] }}</span></div>
            @endif
        @endforeach
    </div>
</div>
