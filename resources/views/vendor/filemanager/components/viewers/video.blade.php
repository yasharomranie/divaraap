
@php
    use MWGuerra\FileManager\Enums\FileManagerIcon;
    $url = $url ?? null;
    $item = $item ?? null;
    $viewerKey = 'vid-' . md5($url ?? '');
@endphp

<div
    wire:key="{{ $viewerKey }}"
    x-data="{
        loaded: false,
        error: false,
        loading: true,
        checkLoaded() {
            const video = this.$refs.previewVideo;
            if (video && video.readyState >= 1) {
                this.loaded = true;
                this.loading = false;
                video.play().catch(() => {});
            }
        },
        handleLoaded() {
            this.loaded = true;
            this.loading = false;
            this.$refs.previewVideo?.play().catch(() => {});
        }
    }"
    x-init="$nextTick(() => checkLoaded())"
    class="flex items-center justify-center bg-black rounded-lg overflow-hidden min-h-[200px]"
>
    
    <div x-show="loading && !error" class="flex flex-col items-center justify-center text-white">
        <div class="w-8 h-8 border-4 border-white border-t-transparent rounded-full animate-spin mb-2"></div>
        <span class="text-sm text-gray-300">{{ __('admin::filemanager.loading_video') }}</span>
    </div>

    
    <div x-show="error" x-cloak class="flex flex-col items-center justify-center text-center p-6 text-white">
        {!! FileManagerIcon::VideoCameraSlash->render('w-12 h-12 text-amber-500 mb-3') !!}
        <p class="font-medium">{{ __('admin::filemanager.video_unavailable') }}</p>
        <p class="text-sm text-gray-400 mt-1">
            {{ __('admin::filemanager.this_video_could_not_be_loaded_it_may_be_inaccessible_or_the_url_has_expired') }}
        </p>
        @if($url)
            <a
                href="{{ $url }}"
                download="{{ $item?->getName() ?? 'download' }}"
                class="mt-4 inline-flex items-center gap-2 px-4 py-2 bg-primary-600 hover:bg-primary-500 text-white rounded-lg transition-colors text-sm"
            >
                {!! FileManagerIcon::ArrowDownTray->render('w-4 h-4') !!}
                {{ __('admin::filemanager.download_video') }}
            </a>
        @endif
    </div>

    
    <video
        x-ref="previewVideo"
        x-show="loaded && !error"
        x-cloak
        controls
        class="max-w-full max-h-[65vh]"
        preload="metadata"
        @if($item && method_exists($item, 'getThumbnail') && $item->getThumbnail())
            poster="{{ $item->getThumbnail() }}"
        @endif
        x-on:loadeddata="handleLoaded()"
        x-on:error="error = true; loading = false"
    >
        <source src="{{ $url }}" type="video/mp4">
        <source src="{{ $url }}" type="video/webm">
        <source src="{{ $url }}" type="video/ogg">
        {{ __('admin::filemanager.your_browser_does_not_support_the_video_tag') }}
    </video>
</div>
