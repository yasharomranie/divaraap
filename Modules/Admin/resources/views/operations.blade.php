<x-filament-panels::page>
    <style>
        .ops-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(min(100%,320px),1fr));gap:20px}.ops-card{background:white;color:#1d1d1f;border:1px solid #d6d6db;border-radius:14px;padding:18px;min-width:0}.ops-card h2{font-size:16px;font-weight:650;margin-bottom:12px}.ops-item{padding:12px 0;border-top:1px solid #eee}.ops-item p{font-size:13px;line-height:1.8;overflow-wrap:anywhere}.ops-actions{display:flex;gap:10px;margin-top:10px}.ops-actions button,.ops-link{min-height:44px;padding:8px 14px;border-radius:8px;border:1px solid #b8bdc7;font-size:13px}.ops-actions button:first-child{background:#1557ff;color:white;border-color:#1557ff}.ops-note{width:100%;min-height:72px;border:1px solid #b8bdc7;border-radius:10px;padding:10px;font-size:16px}.ops-table{width:100%;border-collapse:collapse;font-size:13px;text-align:start}.ops-table td,.ops-table th{padding:10px;border-bottom:1px solid #eee;text-align:start}.ops-scroll{max-width:100%;overflow-x:auto}.ops-muted{color:#62636b;font-size:12px;line-height:1.8}.ops-card :is(button,a,textarea):focus-visible{outline:2px solid #1557ff;outline-offset:3px}.dark .ops-card{background:#18181b;color:#fafafa;border-color:#52525b}.dark .ops-note{background:#18181b;color:#fafafa}.dark .ops-muted{color:#d4d4d8}.ops-photo{width:100%;max-height:160px;object-fit:contain;margin:8px 0}
    </style>
    @can('advertising.manage')<a class="ops-link" href="{{ url('/admin/banners') }}">{{ __('advertising::admin.banners') }}</a>@endcan
    @if($this->queues !== [])
        <div><label for="ops-note">{{ __('admin::operations.note') }}</label><textarea id="ops-note" class="ops-note" maxlength="1000" wire:model="resolutionNote"></textarea></div>
    @endif
    <div class="ops-grid">
        @foreach($this->queues as $kind => $records)
            <section class="ops-card"><h2>{{ __('admin::operations.'.$kind) }}</h2><p class="ops-muted">{{ __('admin::operations.queue_limit') }}</p>
                @forelse($records as $record)
                    <article class="ops-item" wire:key="ops-{{ $kind }}-{{ $record->id }}">
                        <strong dir="auto">#{{ $record->id }} — {{ $record->title ?: ($record->reason ?? '') }}</strong>
                        <p dir="auto">{{ \Illuminate\Support\Str::limit((string) ($record->description ?? $record->body ?? $record->details ?? $record->alt), 500) }}</p>
                        @if($kind === 'listings' && ($photo = $record->primaryImageUrl('card')))<img class="ops-photo" src="{{ $photo }}" alt="{{ $record->title }}" loading="lazy">@endif
                        @if($kind === 'advertising')<img class="ops-photo" src="{{ $record->imageUrl() }}" alt="{{ $record->alt }}" loading="lazy">@endif
                        <div class="ops-actions">
                            <button type="button" wire:click="review('{{ $kind }}', {{ $record->id }}, 'approve')" wire:loading.attr="disabled">{{ __('admin::operations.'.($kind === 'reports' ? 'resolve' : 'approve')) }}</button>
                            <button type="button" wire:click="review('{{ $kind }}', {{ $record->id }}, 'reject')" wire:loading.attr="disabled">{{ __('admin::operations.'.($kind === 'reports' ? 'dismiss' : 'reject')) }}</button>
                        </div>
                    </article>
                @empty <p>{{ __('admin::operations.empty') }}</p>@endforelse
            </section>
        @endforeach
    </div>
    @can('operations.analytics')
        <section class="ops-card"><h2>{{ __('admin::operations.funnel') }}</h2><p class="ops-muted">{{ __('admin::operations.measurement') }}</p><table class="ops-table"><thead><tr><th>{{ __('admin::operations.step') }}</th><th>{{ __('admin::operations.count') }}</th></tr></thead><tbody>
            @foreach($this->funnel as $row)<tr><td>{{ __('admin::operations.step_'.$row['value']) }}</td><td>{{ \App\Support\PersianLocale::number($row['total']) }}</td></tr>@endforeach
        </tbody></table></section>
        <section class="ops-card"><h2>{{ __('admin::operations.searches') }}</h2><div class="ops-scroll"><table class="ops-table"><thead><tr><th>{{ __('admin::operations.query') }}</th><th>{{ __('admin::operations.count') }}</th></tr></thead><tbody>
            @foreach($this->searches as $row)<tr><td dir="auto">{{ $row['value'] === '[filters]' ? __('admin::operations.filters_only') : $row['value'] }}<p class="ops-muted">{{ $row['category'] }} / {{ $row['country'] }} / {{ $row['city'] }}</p></td><td>{{ \App\Support\PersianLocale::number($row['total']) }}</td></tr>@endforeach
        </tbody></table></div></section>
    @endcan
    @can('operations.audit')
        <section class="ops-card"><h2>{{ __('admin::operations.history') }}</h2><div class="ops-scroll"><table class="ops-table"><thead><tr><th>{{ __('admin::operations.event') }}</th><th>{{ __('admin::operations.record') }}</th><th>{{ __('admin::operations.actor') }}</th><th>{{ __('admin::operations.time') }}</th></tr></thead><tbody>
            @foreach($this->history as $row)<tr><td>{{ $row['description'] }}</td><td>{{ class_basename($row['subject_type'] ?? '') }} #{{ $row['subject_id'] }}</td><td>{{ $row['causer_id'] ?? '—' }}</td><td>{{ \App\Support\PersianLocale::date(\Illuminate\Support\Carbon::parse($row['created_at']), true) }}</td></tr>@endforeach
        </tbody></table></div></section>
    @endcan
</x-filament-panels::page>
