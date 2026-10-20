@php($deadline = $banner->effectiveDeadline())
<div class="advertising-deadline" data-ad-deadline="{{ $banner->id }}" @if(!($isPreview ?? false)) data-behavior="advertising-deadline" data-deadline="{{ $deadline->toIso8601String() }}" data-server-now="{{ now()->toIso8601String() }}" data-locale="{{ app()->getLocale() }}" data-remaining="{{ __('advertising::deadline.remaining') }}" data-day="{{ __('advertising::deadline.day') }}" data-hour="{{ __('advertising::deadline.hour') }}" data-minute="{{ __('advertising::deadline.minute') }}" data-under-minute="{{ __('advertising::deadline.under_minute') }}" @endif>
    <picture class="advertising-deadline__media">
        @if($banner->mobile_image)<source media="(max-width: 1023px)" srcset="{{ $banner->imageUrl(true) }}">@endif
        <img class="advertising-deadline__cover" src="{{ $banner->imageUrl() }}" alt="{{ $banner->alt }}" width="400" height="400" loading="lazy" decoding="async">
    </picture>
    <div class="advertising-deadline__body">
        <p class="text-meta" dir="auto">{{ $banner->title }}</p>
        <h2 dir="auto">{{ $banner->advertisedDeadlineListing()['title'] }}</h2>
        <p class="text-price" dir="auto">{{ $banner->advertisedDeadlineListing()['price'] }}</p>
        <p class="text-meta">{{ __('advertising::deadline.ends') }} <time datetime="{{ $deadline->toIso8601String() }}">{{ \App\Support\PersianLocale::date($deadline->copy()->setTimezone('Asia/Tehran'), true) }}</time> {{ __('advertising::deadline.tehran') }}</p>
        <p class="advertising-deadline__remaining" data-deadline-remaining aria-hidden="true"></p>
        <span class="advertising-deadline__action">{{ __('advertising::deadline.action') }}</span>
        <p class="text-meta">{{ __('advertising::deadline.note') }}</p>
    </div>
</div>
