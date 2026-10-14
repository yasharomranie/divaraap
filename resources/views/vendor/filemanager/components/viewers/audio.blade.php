
@php
    use MWGuerra\FileManager\Enums\FileManagerIcon;
    $url = $url ?? null;
    $item = $item ?? null;
    $viewerKey = 'aud-' . md5($url ?? '');
@endphp

<div
    wire:key="{{ $viewerKey }}"
    x-data="{
        loaded: false,
        error: false,
        loading: true,
        checkLoaded() {
            const audio = this.$refs.previewAudio;
            if (audio && audio.readyState >= 1) {
                this.loaded = true;
                this.loading = false;
                audio.play().catch(() => {});
            }
        },
        handleLoaded() {
            this.loaded = true;
            this.loading = false;
            this.$refs.previewAudio?.play().catch(() => {});
        }
    }"
    x-init="$nextTick(() => checkLoaded())"
    class="flex flex-col items-center justify-center p-8 bg-gradient-to-br from-purple-50 to-indigo-50 dark:from-purple-900/20 dark:to-indigo-900/20 rounded-lg min-h-[200px]"
>
    
    <div x-show="loading && !error" class="flex flex-col items-center justify-center">
        <div class="w-8 h-8 border-4 border-purple-500 border-t-transparent rounded-full animate-spin mb-2"></div>
        <span class="text-sm text-gray-500 dark:text-gray-400">{{ __('admin::filemanager.loading_audio') }}</span>
    </div>

    
    <div x-show="error" x-cloak class="flex flex-col items-center justify-center text-center p-6">
        {!! FileManagerIcon::SpeakerXMark->render('w-12 h-12 text-amber-500 mb-3') !!}
        <p class="text-gray-700 dark:text-gray-300 font-medium">{{ __('admin::filemanager.audio_unavailable') }}</p>
        <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">
            {{ __('admin::filemanager.this_audio_file_could_not_be_loaded_it_may_be_inaccessible_or_the_url_has_expired') }}
        </p>
        @if($url)
            <a
                href="{{ $url }}"
                download="{{ $item?->getName() ?? 'download' }}"
                class="mt-4 inline-flex items-center gap-2 px-4 py-2 bg-primary-600 hover:bg-primary-500 text-white rounded-lg transition-colors text-sm"
            >
                {!! FileManagerIcon::ArrowDownTray->render('w-4 h-4') !!}
                {{ __('admin::filemanager.download_audio') }}
            </a>
        @endif
    </div>

    
    <div x-show="loaded && !error" x-cloak class="w-full flex flex-col items-center">
        <div class="mb-6 p-8 bg-white dark:bg-gray-800 rounded-full shadow-lg">
            {!! FileManagerIcon::MusicalNote->render('w-24 h-24 text-purple-500') !!}
        </div>

        @if($item)
            <p class="mb-4 text-lg font-medium text-gray-900 dark:text-white">{{ $item->getName() }}</p>
        @endif

        <audio
            x-ref="previewAudio"
            controls
            class="w-full max-w-md"
            preload="metadata"
            x-on:loadeddata="handleLoaded()"
            x-on:error="error = true; loading = false"
        >
            <source src="{{ $url }}" type="audio/mpeg">
            <source src="{{ $url }}" type="audio/wav">
            <source src="{{ $url }}" type="audio/ogg">
            {{ __('admin::filemanager.your_browser_does_not_support_the_audio_element') }}
        </audio>
    </div>
</div>
