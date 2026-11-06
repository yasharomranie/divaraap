<x-filament-panels::page>
    <div class="operational-monitor">
        <style>
            .operational-monitor{display:grid;gap:20px;min-width:0}.monitor-tools{display:flex;gap:12px;flex-wrap:wrap;align-items:center}.monitor-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(min(100%,230px),1fr));gap:12px}.monitor-card{padding:18px;border:1px solid #d1d5db;border-radius:12px;background:#fff;color:#111827;min-width:0;overflow-wrap:anywhere}.monitor-card h2{font-weight:700}.monitor-card p{margin-top:8px}.monitor-card small{display:block;margin-top:8px;color:#4b5563}.monitor-link{color:#075985;text-decoration:underline;display:inline-flex;align-items:center;min-height:44px}.monitor-link:focus-visible{outline:3px solid #0284c7;outline-offset:3px}.monitor-history{display:grid;gap:12px}.monitor-meta{display:flex;flex-wrap:wrap;gap:12px;margin-block:10px}.monitor-note{white-space:pre-wrap;overflow-wrap:anywhere}.monitor-status{font-weight:700}.operational-monitor bdi{direction:ltr;unicode-bidi:isolate;white-space:nowrap;font-size:.8125rem}
        </style>
        <p>{{ __('admin::monitor.help') }}</p>
        <div class="monitor-tools">
            <a class="monitor-link" href="{{ \Modules\Admin\Filament\Pages\ManageOperationalMonitor::getUrl() }}">{{ __('admin::monitor.settings') }}</a>
            <x-filament::button wire:click="sample" wire:loading.attr="disabled">{{ __('admin::monitor.scan') }}</x-filament::button>
        </div>
        <x-filament::section>
            <p class="monitor-status">{{ __('admin::monitor.'.(! $monitor->enabled ? 'disabled' : ($monitor->freshSample() ? 'recent' : 'stale_sample'))) }}</p>
            <p>{{ __('admin::monitor.last_checked') }}: <bdi>{{ $monitor->last_checked_at?->format('Y-m-d H:i:s') ?? '—' }}</bdi></p>
            <p>{{ __('admin::monitor.last_scheduled') }}: <bdi>{{ $monitor->last_scheduled_at?->format('Y-m-d H:i:s') ?? '—' }}</bdi></p>
        </x-filament::section>
        <div class="monitor-grid">
            @foreach (\Modules\Admin\Models\OperationalMonitor::CHECKS as $key)
                @php($check = $checks[$key] ?? null)
                <section class="monitor-card">
                    <h2>{{ __('admin::monitor.check_'.$key) }}</h2>
                    <p class="monitor-status">{{ __('admin::monitor.status_'.($check?->status ?? 'unknown')) }}</p>
                    <p>{{ __('admin::monitor.code_'.($check?->code ?? 'not_sampled')) }}</p>
                    @if ($check?->value !== null)<p>{{ __('admin::monitor.unit_'.(in_array($key, ['scheduler', 'deployment'], true) ? 'seconds' : ($key === 'disk' ? 'mb' : 'count'))) }}: <bdi>{{ $check->value }}</bdi></p>@endif
                    <small><bdi>{{ $check?->sampled_at ?? '—' }}</bdi></small>
                </section>
            @endforeach
        </div>
        <h2>{{ __('admin::monitor.history') }}</h2>
        <p>{{ __('admin::monitor.ack_help') }}</p>
        <div class="monitor-history">
            @forelse ($incidents as $incident)
                <article class="monitor-card">
                    <h2>#{{ $incident->id }} · {{ __('admin::monitor.check_'.$incident->check_key) }}</h2>
                    <div class="monitor-meta">
                        <strong>{{ __('admin::monitor.'.($incident->resolved_at ? 'resolved' : 'open')) }}</strong>
                        <span>{{ __('admin::monitor.opened') }}: <bdi>{{ $incident->opened_at->format('Y-m-d H:i:s') }}</bdi></span>
                        @if ($incident->resolved_at)<span>{{ __('admin::monitor.resolved') }}: <bdi>{{ $incident->resolved_at->format('Y-m-d H:i:s') }}</bdi></span>@endif
                    </div>
                    @if ($incident->acknowledged_at)
                        <p>{{ __('admin::monitor.acknowledged') }} · #{{ $incident->acknowledged_by }} · <bdi>{{ $incident->acknowledged_at->format('Y-m-d H:i:s') }}</bdi></p>
                        <p class="monitor-note">{{ $incident->note }}</p>
                    @elseif (! $incident->resolved_at)
                        {{ ($this->acknowledgeAction)(['id' => $incident->id, 'version' => $incident->version]) }}
                    @endif
                </article>
            @empty
                <p>{{ __('admin::monitor.no_incidents') }}</p>
            @endforelse
        </div>
        {{ $incidents->links() }}
    </div>
</x-filament-panels::page>
