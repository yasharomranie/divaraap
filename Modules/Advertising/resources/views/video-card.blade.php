<div class="advertising-video" data-ad-video-card="{{ $banner->id }}">
    @if(!($isPreview ?? false))
        <video class="advertising-video__player" data-ad-video controls playsinline preload="none" poster="{{ $banner->imageUrl(($device ?? '') === 'mobile') }}" @if($banner->mobile_image) data-mobile-poster="{{ $banner->imageUrl(true) }}" @endif width="1280" height="720" aria-label="{{ $banner->title }}">
            <source src="{{ $banner->advertisedVideo()['url'] }}" type="video/mp4">
            @if($banner->video_captions)<track kind="captions" src="{{ route('advertising.video-captions', ['banner' => $banner->id, 'version' => $banner->videoSignature()]) }}" srclang="{{ $banner->video_caption_language }}" label="{{ __('advertising::video.captions') }}" default>@endif
            {{ __('advertising::video.unsupported') }}
        </video>
        <div class="advertising-video__fallback" data-ad-video-fallback hidden>
            <img src="{{ $banner->imageUrl() }}" alt="{{ $banner->alt }}" width="1280" height="720" loading="lazy" decoding="async">
            <p class="text-meta" role="status">{{ __('advertising::video.error') }}</p>
        </div>
    @else
        <img class="advertising-video__poster" src="{{ $banner->imageUrl(($device ?? '') === 'mobile') }}" alt="{{ $banner->alt }}" width="1280" height="720" loading="lazy" decoding="async">
    @endif
    <div class="advertising-video__body">
        <h2 dir="auto">{{ $banner->title }}</h2>
        <p class="text-meta" dir="auto">{{ $banner->advertisedVideo()['listing_title'] }}</p>
        <p class="text-meta">{{ __('advertising::video.optional') }}</p>
        <details class="advertising-video__transcript"><summary>{{ __('advertising::video.read_transcript') }}</summary><p dir="auto">{{ $banner->video_transcript }}</p></details>
        @if(!($isPreview ?? false))<a class="advertising-video__action" href="{{ $banner->destination() }}" @if($banner->new_tab) target="_blank" @endif rel="sponsored noopener noreferrer">{{ __('advertising::video.destination') }}</a>@endif
    </div>
</div>
