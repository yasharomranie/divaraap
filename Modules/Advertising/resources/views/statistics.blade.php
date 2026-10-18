<x-filament-panels::page>
    <style>
        .ad-statistics{min-width:0;max-width:100%;padding:20px;border:1px solid #d6d6db;border-radius:14px;background:#fff;color:#1d1d1f}.ad-statistics__filters{display:flex;gap:16px;flex-wrap:wrap;margin-bottom:20px}.ad-statistics label{display:grid;gap:8px;flex:1;min-width:min(100%,200px);font-size:14px}.ad-statistics select{min-height:44px;border:1px solid #84848c;border-radius:8px;max-width:100%;background:inherit;color:inherit}.ad-statistics select:focus-visible{outline:2px solid #1557ff;outline-offset:3px}.ad-statistics__scroll{overflow-x:auto;max-width:100%}.ad-statistics table{border-collapse:collapse;width:100%;font-size:14px}.ad-statistics th,.ad-statistics td{white-space:nowrap;padding:12px;text-align:start;border-bottom:1px solid #d6d6db;}.ad-statistics th:first-child,.ad-statistics td:first-child{white-space:normal;overflow-wrap:anywhere;min-width:90px}.ad-statistics details summary{cursor:pointer;min-height:44px;padding:12px 0;font-size:14px}.ad-statistics details summary:focus-visible{outline:2px solid #1557ff;outline-offset:3px}@media(max-width:768px){.ad-statistics{margin-bottom:96px}}.ad-statistics p,.ad-statistics caption{font-size:13px;line-height:1.9;text-align:start;margin-bottom:16px;color:#55555c}.dark .ad-statistics{background:#18181b;color:#fafafa;border-color:#52525b}.dark .ad-statistics p,.dark .ad-statistics caption{color:#d4d4d8}
    </style>
    <section class="ad-statistics">
        <p>{{ __('advertising::metrics.definition') }}</p>
        <div class="ad-statistics__filters">
            <label for="ad-campaign">{{ __('advertising::campaign.campaign') }}
                <select id="ad-campaign" wire:model.live="campaignId"><option value="">{{ __('advertising::metrics.all') }}</option>@foreach($this->campaigns as $id => $title)<option value="{{ $id }}">{{ $title }}</option>@endforeach</select>
            </label>
            <label for="ad-dimension">{{ __('advertising::metrics.dimension') }}
                <select id="ad-dimension" wire:model.live="dimension">@foreach(['event_date', 'banner_id', 'placement', 'device'] as $field)<option value="{{ $field }}">{{ __('advertising::metrics.'.$field) }}</option>@endforeach</select>
            </label>
        </div>
        <div class="ad-statistics__scroll" tabindex="0" role="region" aria-label="{{ __('advertising::metrics.title') }}">
            <table><caption>{{ __('advertising::metrics.period') }}</caption><thead><tr><th scope="col">{{ __('advertising::metrics.'.$dimension) }}</th><th scope="col">{{ __('advertising::metrics.impressions') }}</th><th scope="col">{{ __('advertising::metrics.clicks') }}</th><th scope="col">{{ __('advertising::metrics.ctr') }}</th></tr></thead><tbody>
            @forelse($this->rows as $row)
                <tr><th scope="row">{{ $dimension === 'event_date' ? \App\Support\PersianLocale::date($row['label']) : (in_array($dimension, ['placement', 'device'], true) ? __('advertising::admin.'.$row['label']) : $row['label']) }}</th><td>{{ \App\Support\PersianLocale::number($row['impressions']) }}</td><td>{{ \App\Support\PersianLocale::number($row['clicks']) }}</td><td>{{ $row['ctr'] === null ? '—' : \App\Support\PersianLocale::number($row['ctr']).'٪' }}</td></tr>
            @empty <tr><td colspan="4">{{ __('advertising::metrics.empty') }}</td></tr>@endforelse
            </tbody></table>
        </div>
        <details><summary>{{ __('advertising::metrics.notes') }}</summary><p>{{ __('advertising::metrics.limitations') }} {{ __('advertising::metrics.tracking_toggle') }}</p></details>
    </section>
</x-filament-panels::page>
