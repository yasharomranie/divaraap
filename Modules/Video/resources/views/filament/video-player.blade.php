@php
    $video = $video ?? null;
    $url = $video?->playableUrl();
@endphp

<div class="space-y-4">
    @if($url)
        <video
            class="w-full rounded-2xl bg-slate-950 max-h-[32rem]"
            controls
            preload="metadata"
            src="{{ $url }}"
            type="{{ $video?->previewMimeType() }}"
        ></video>
    @else
        <div class="rounded-2xl border border-dashed border-slate-300 bg-slate-50 px-4 py-10 text-center text-sm text-slate-500">
            {{ __('video::admin.no_playable_file') }}
        </div>
    @endif

    @if($video)
        <div class="grid gap-3 md:grid-cols-2">
            <div class="rounded-xl border border-slate-200 bg-white px-4 py-3">
                <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">{{ __('video::admin.title') }}</p>
                <p class="mt-1 text-sm font-medium text-slate-800"><bdi>{{ $video->titleLabel() }}</bdi></p>
            </div>
            <div class="rounded-xl border border-slate-200 bg-white px-4 py-3">
                <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">{{ __('video::admin.status') }}</p>
                <p class="mt-1 text-sm font-medium text-slate-800">{{ $video->statusLabel() }}</p>
            </div>
            <div class="rounded-xl border border-slate-200 bg-white px-4 py-3">
                <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">{{ __('video::admin.listing') }}</p>
                <p class="mt-1 text-sm font-medium text-slate-800"><bdi>{{ $video->listing?->title ?? '-' }}</bdi></p>
            </div>
            <div class="rounded-xl border border-slate-200 bg-white px-4 py-3">
                <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">{{ __('video::admin.resolution') }}</p>
                <p class="mt-1 text-sm font-medium text-slate-800"><bdi>{{ $video->resolutionLabel() }}</bdi></p>
            </div>
            <div class="rounded-xl border border-slate-200 bg-white px-4 py-3">
                <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">{{ __('video::admin.duration') }}</p>
                <p class="mt-1 text-sm font-medium text-slate-800"><bdi>{{ $video->durationLabel() }}</bdi></p>
            </div>
            <div class="rounded-xl border border-slate-200 bg-white px-4 py-3">
                <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">{{ __('video::admin.size') }}</p>
                <p class="mt-1 text-sm font-medium text-slate-800"><bdi>{{ $video->sizeLabel() }}</bdi></p>
            </div>
        </div>

        @if(filled($video->description))
            <div class="rounded-xl border border-slate-200 bg-white px-4 py-3">
                <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">{{ __('video::admin.description') }}</p>
                <p class="mt-1 text-sm text-slate-700"><span dir="auto">{{ $video->description }}</span></p>
            </div>
        @endif

        @if(filled($video->processing_error))
            <div class="rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700">
                {{ $video->processing_error }}
            </div>
        @endif
    @endif
</div>
