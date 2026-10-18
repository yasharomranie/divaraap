<x-filament-panels::page>
    <style>
        .ad-preview{min-width:0;max-width:100%;padding:18px;border:1px solid #d6d6db;border-radius:14px;background:#fff;color:#1d1d1f}.ad-preview__filters{display:flex;gap:16px;flex-wrap:wrap;margin:16px 0}.ad-preview label{display:grid;gap:8px;flex:1;min-width:min(100%,190px);font-size:14px}.ad-preview select{min-height:44px;max-width:100%;border:1px solid #84848c;border-radius:8px;background:inherit;color:inherit}.ad-preview__scroll{max-width:100%;overflow:auto;border:1px solid #d6d6db;border-radius:10px;background:#f5f5f7}.ad-preview iframe{display:block;min-width:0;max-width:none;height:650px;border:0;background:#fff}.ad-preview p{font-size:13px;line-height:1.9;margin:8px 0;color:#55555c}.ad-preview :is(select,.ad-preview__scroll):focus-visible{outline:2px solid #1557ff;outline-offset:3px}.dark .ad-preview{background:#18181b;color:#fafafa;border-color:#52525b}.dark .ad-preview p{color:#d4d4d8}@media(max-width:768px){.ad-preview{margin-bottom:96px}}
    </style>
    <section class="ad-preview">
        <p>{{ __('advertising::preview.hint') }}</p>
        <div class="ad-preview__filters">
            <label for="preview-banner">{{ __('advertising::admin.banner') }}<select id="preview-banner" wire:model.live="bannerId"><option value="">{{ __('advertising::preview.choose') }}</option>@foreach($this->banners as $id => $title)<option value="{{ $id }}">{{ $title }}</option>@endforeach</select></label>
            <label for="preview-device">{{ __('advertising::admin.device') }}<select id="preview-device" wire:model.live="device">@foreach(['mobile', 'desktop'] as $deviceOption)<option value="{{ $deviceOption }}">{{ __('advertising::admin.'.$deviceOption) }}</option>@endforeach</select></label>
            <label for="preview-placement">{{ __('advertising::admin.placement') }}<select id="preview-placement" wire:model.live="placement">@foreach(\Modules\Advertising\Models\BannerPlacement::KEYS as $key)<option value="{{ $key }}">{{ __('advertising::admin.'.$key) }}</option>@endforeach</select></label>
        </div>
        @if($url = $this->previewUrl)
            <p>{{ __('advertising::preview.scroll_hint') }}</p>
            <div class="ad-preview__scroll" tabindex="0" role="region" aria-label="{{ __('advertising::preview.title') }}">
                <iframe wire:key="{{ $url }}" src="{{ $url }}" title="{{ __('advertising::preview.frame_title') }}" sandbox="allow-same-origin" referrerpolicy="same-origin" width="{{ $this->device === 'desktop' ? 1200 : 390 }}" height="650"></iframe>
            </div>
        @else <p>{{ __('advertising::preview.choose') }}</p>@endif
    </section>
</x-filament-panels::page>
