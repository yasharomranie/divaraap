<x-filament-panels::page>
    <p>{{ __('admin::security.privacy') }}</p>
    <div style="display:grid;gap:16px">
        @forelse($events as $event)
            <x-filament::section>
                <p>{{ __('admin::security.user') }} #{{ $event->user_id }} · {{ __('admin::security.event_'.$event->kind) }}</p>
                <time datetime="{{ $event->created_at->toIso8601String() }}">{{ $event->created_at->timezone('Asia/Tehran')->format('Y/m/d H:i') }}</time>
            </x-filament::section>
        @empty
            <p>{{ __('admin::security.empty') }}</p>
        @endforelse
    </div>
    {{ $events->links('components.pagination') }}
</x-filament-panels::page>
