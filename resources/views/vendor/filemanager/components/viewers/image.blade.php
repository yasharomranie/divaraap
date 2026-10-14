
@php
    use MWGuerra\FileManager\Enums\FileManagerIcon;
    $url = $url ?? null;
    $item = $item ?? null;
    $viewerKey = 'img-' . md5($url ?? '');
@endphp

<div
    wire:key="{{ $viewerKey }}"
    x-data="{
        loaded: false,
        error: false,
        loading: true,
        checkLoaded() {
            const img = this.$refs.previewImage;
            if (img && img.complete) {
                if (img.naturalWidth > 0) {
                    this.loaded = true;
                    this.loading = false;
                } else {
                    this.error = true;
                    this.loading = false;
                }
            }
        }
    }"
    x-init="$nextTick(() => checkLoaded())"
    class="flex items-center justify-center bg-gray-100 dark:bg-gray-800 rounded-lg p-4 min-h-[200px]"
>
    
    <div x-show="loading && !error" class="flex flex-col items-center justify-center">
        <div class="w-8 h-8 border-4 border-primary-500 border-t-transparent rounded-full animate-spin mb-2"></div>
        <span class="text-sm text-gray-500 dark:text-gray-400">{{ __('admin::filemanager.loading_preview') }}</span>
    </div>

    
    <div x-show="error" x-cloak class="flex flex-col items-center justify-center text-center p-6">
        {!! FileManagerIcon::ExclamationTriangle->render('w-12 h-12 text-amber-500 mb-3') !!}
        <p class="text-gray-700 dark:text-gray-300 font-medium">{{ __('admin::filemanager.preview_not_available') }}</p>
        <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">
            {{ __('admin::filemanager.this_image_could_not_be_loaded_it_may_be_inaccessible_or_the_url_has_expired') }}
        </p>
        @if($url)
            <a
                href="{{ $url }}"
                download="{{ $item?->getName() ?? 'download' }}"
                class="mt-4 inline-flex items-center gap-2 px-4 py-2 bg-primary-600 hover:bg-primary-500 text-white rounded-lg transition-colors text-sm"
            >
                {!! FileManagerIcon::ArrowDownTray->render('w-4 h-4') !!}
                {{ __('admin::filemanager.try_download') }}
            </a>
        @endif
    </div>

    
    <img
        x-ref="previewImage"
        x-show="loaded && !error"
        x-cloak
        src="{{ $url }}"
        alt="{{ $item?->getName() ?? __('admin::filemanager.image_preview') }}"
        class="max-w-full max-h-[65vh] object-contain rounded"
        x-on:load="loaded = true; loading = false"
        x-on:error="error = true; loading = false"
    />
</div>
