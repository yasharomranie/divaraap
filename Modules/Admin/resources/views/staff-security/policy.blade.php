<x-filament-panels::page>
    <p>{{ __('admin::security.policy_help') }}</p>
    <a href="{{ route('staff-security.setup') }}" class="fi-link">{{ __('admin::security.prepare') }}</a>
    <form wire:submit="save" style="display:grid;gap:24px">
        {{ $this->form }}
        <x-filament::button type="submit">{{ __('admin::security.save') }}</x-filament::button>
    </form>
</x-filament-panels::page>
