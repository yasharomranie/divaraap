
@php
    use MWGuerra\FileManager\Enums\FileManagerIcon;
    $url = $url ?? null;
    $item = $item ?? null;
    $fileType = $fileType ?? null;
@endphp

<div class="flex flex-col items-center justify-center p-12 text-gray-500 dark:text-gray-400">
    @if($fileType)
        <x-dynamic-component
            :component="$fileType->icon()"
            class="w-20 h-20 mb-4 {{ $fileType->iconColor() }}"
        />
    @else
        {!! FileManagerIcon::EyeSlash->render('w-20 h-20 mb-4') !!}
    @endif

    <p class="text-lg font-medium text-gray-700 dark:text-gray-300">
        {{ __('admin::filemanager.preview_not_available') }}
    </p>
    <p class="text-sm mt-1 text-center max-w-md text-gray-600 dark:text-gray-400">
        {{ __('admin::filemanager.this_file_type_cannot_be_previewed_in_the_browser') }}
        @if($fileType)
            <br><span class="text-gray-500 dark:text-gray-400">{{ __('admin::filemanager.type') }}: {{ \App\Support\Filament\FileManagerText::translate($fileType->label()) }}</span>
        @endif
    </p>

    @if($url)
        <a
            href="{{ $url }}"
            download="{{ $item?->name }}"
            class="mt-6 inline-flex items-center gap-2 px-4 py-2 bg-primary-600 hover:bg-primary-500 text-white rounded-lg transition-colors"
        >
            {!! FileManagerIcon::ArrowDownTray->render('w-5 h-5') !!}
            {{ __('admin::filemanager.download_file') }}
        </a>
    @endif

    @if($item)
        <div class="mt-4 text-xs text-gray-500 dark:text-gray-400">
            @if($item->size)
                <span>{{ $item->getFormattedSize() }}</span>
            @endif
        </div>
    @endif
</div>
