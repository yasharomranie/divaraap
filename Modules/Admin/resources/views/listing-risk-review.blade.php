<x-filament-panels::page>
    <style>
        .risk-review{display:grid;gap:16px;max-width:100%;min-width:0}.risk-card{padding:16px;border:1px solid #d6d6db;border-radius:14px;background:#fff;color:#1d1d1f;min-width:0;overflow-wrap:anywhere}.risk-card p,.risk-card li{line-height:1.9;margin-block:8px}.risk-card ul{padding-inline-start:20px;list-style:disc}.risk-review a,.risk-review button,.risk-review summary{display:inline-flex;align-items:center;min-height:44px;padding:8px 12px;border:1px solid #b8bdc7;border-radius:8px;margin:4px}.risk-review :is(a,button,summary):focus-visible{outline:2px solid #1557ff;outline-offset:3px}.risk-review h2{font-size:16px;font-weight:650}.dark .risk-card{background:#18181b;color:#fafafa;border-color:#52525b}
    </style>
    <div class="risk-review">
        <section class="risk-card">
            <p>{{ __('listing::risk.hint') }}</p>
            <p>{{ __('listing::risk.scope', ['count' => \App\Support\PersianLocale::digits($this->snapshot['scanned'])]) }}</p>
            <button type="button" wire:click="refreshSignals" wire:loading.attr="disabled">{{ __('listing::risk.refresh') }}</button>
            <a href="{{ url('/admin/operations') }}">{{ __('listing::risk.moderation') }}</a>
        </section>
        @forelse($this->snapshot['items'] as $item)
            <article class="risk-card" id="risk-listing-{{ $item['id'] }}" data-risk-listing="{{ $item['id'] }}" wire:key="risk-{{ $item['id'] }}">
                <h2 dir="auto">#{{ $item['id'] }} — {{ $item['title'] }}</h2>
                <p>{{ __('listing::risk.status') }}: {{ __('listing::admin.'.$item['status']) }}</p>
                <details><summary>{{ __('listing::risk.content') }}</summary><p dir="auto" style="white-space:pre-wrap">{{ $item['description'] }}</p></details>
                <ul>
                    @foreach($item['signals'] as $signal => $peers)
                        <li data-risk-signal="{{ $signal }}">
                            <strong>{{ __('listing::risk.'.$signal) }}</strong>
                            <p>{{ __('listing::risk.'.$signal.'_hint') }}</p>
                            @if($peers !== [])
                                <p>{{ __('listing::risk.matches') }}:
                                    @foreach($peers as $peerId)
                                        @if(auth()->user()->hasRole('admin'))
                                            <a href="{{ url('/admin/listings/'.$peerId.'/edit') }}">#{{ $peerId }}</a>
                                        @else
                                            <a dir="ltr" href="#risk-listing-{{ $peerId }}">#{{ $peerId }}</a>
                                        @endif
                                    @endforeach
                                </p>
                            @endif
                        </li>
                    @endforeach
                </ul>
                @if(auth()->user()->hasRole('admin'))<a href="{{ url('/admin/listings/'.$item['id'].'/edit') }}">{{ __('listing::risk.inspect') }}</a>@endif
                @if($item['status'] === 'pending')<a href="{{ url('/admin/operations') }}">{{ __('listing::risk.moderation') }}</a>@else<p>{{ __('listing::risk.active_hint') }}</p>@endif
            </article>
        @empty
            <p class="risk-card">{{ __('listing::risk.empty') }}</p>
        @endforelse
    </div>
</x-filament-panels::page>
