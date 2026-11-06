<x-filament-panels::page>
    <p>{{ __('admin::monitor.settings_help') }}</p>
    <p>{{ __('admin::monitor.probe_help') }}</p>
    <a href="{{ \Modules\Admin\Filament\Pages\OperationalHealth::getUrl() }}" class="fi-link">{{ __('admin::monitor.title') }}</a>
    <form wire:submit="save" style="display:grid;gap:24px;min-width:0">
        {{ $this->form }}
        <x-filament::button type="submit">{{ __('admin::monitor.save') }}</x-filament::button>
    </form>
</x-filament-panels::page>
