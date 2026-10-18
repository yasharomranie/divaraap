<x-filament-panels::page>
    <style>
        .ad-warnings{min-width:0;max-width:100%;padding:18px;border:1px solid #d6d6db;border-radius:14px;background:#fff;color:#1d1d1f}.ad-warnings p{font-size:13px;line-height:1.9;color:#55555c;overflow-wrap:anywhere}.ad-warnings__header{display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap;margin:14px 0}.ad-warnings :is(button,a){display:inline-flex;align-items:center;min-height:44px;border:1px solid #84848c;border-radius:8px;padding:8px 14px;font-size:14px;color:inherit}.ad-warnings button:disabled{opacity:.6}.ad-warnings :is(button,a):focus-visible{outline:2px solid #1557ff;outline-offset:3px}.ad-warning{padding:16px 0;border-top:1px solid #d6d6db}.ad-warning h2{font-size:15px;line-height:1.8;font-weight:650;overflow-wrap:anywhere}.ad-warning a{margin-top:8px}.dark .ad-warnings{background:#18181b;color:#fafafa;border-color:#52525b}.dark .ad-warnings p{color:#d4d4d8}@media(max-width:768px){.ad-warnings{margin-bottom:96px}}
    </style>
    <section class="ad-warnings">
        <p>{{ __('advertising::warnings.hint') }}</p>
        <div class="ad-warnings__header">
            <button type="button" wire:click="scan" wire:loading.attr="disabled"><span wire:loading.remove wire:target="scan">{{ __('advertising::warnings.scan') }}</span><span wire:loading wire:target="scan">{{ __('advertising::warnings.scanning') }}</span></button>
            @if($checkedAt)<p role="status">{{ __('advertising::warnings.last_checked') }}: {{ \App\Support\PersianLocale::date($checkedAt, true) }}</p>@endif
        </div>
        @if($scanFailed)<p role="alert">{{ __('advertising::warnings.failed') }}</p>@endif
        @forelse($this->warnings as $warning)
            <article class="ad-warning" wire:key="warning-{{ $warning->id }}">
                <h2>{{ __('advertising::warnings.'.$warning->code) }}</h2>
                <p dir="auto">{{ $warning->subject_title }}</p>
                <a href="{{ $warning->actionUrl() }}">{{ __('advertising::warnings.edit') }}</a>
            </article>
        @empty <p>{{ __($checkedAt ? 'advertising::warnings.empty' : 'advertising::warnings.no_check') }}</p>@endforelse
        <p>{{ __('advertising::warnings.limit') }}</p>
    </section>
</x-filament-panels::page>
