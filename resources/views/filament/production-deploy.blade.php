<x-filament-panels::page>
    <div wire:poll.5s style="display:grid;gap:24px;min-width:0">
        <x-filament::section style="min-width:0">
            <p style="margin-bottom:20px">{{ __('admin::deployment.description') }}</p>
            <div role="status" aria-live="polite">
                @if ($this->isDeployPending())
                    <x-filament::badge color="warning">{{ __('admin::deployment.pending') }}</x-filament::badge>
                @else
                    <x-filament::button wire:click="requestDeploy" wire:loading.attr="disabled" wire:target="requestDeploy" style="min-height:44px">
                        <span wire:loading.remove wire:target="requestDeploy">{{ __('admin::deployment.run') }}</span>
                        <span wire:loading wire:target="requestDeploy">{{ __('admin::deployment.requesting') }}</span>
                    </x-filament::button>
                @endif
            </div>
        </x-filament::section>
        <x-filament::section :heading="__('admin::deployment.last_log')" style="min-width:0">
            @if ($date = $this->lastDeployAt())
                <p style="margin-bottom:12px">{{ __('admin::deployment.last_deploy') }}: {{ $date }}</p>
            @endif
            @if (($log = $this->lastDeployLog()) !== null)
                <pre dir="ltr" tabindex="0" aria-label="{{ __('admin::deployment.last_log') }}" style="max-height:24rem;max-width:100%;min-width:0;overflow-x:auto;overflow-y:auto;white-space:pre;font-size:1rem;line-height:1.75;margin:0">{{ $log }}</pre>
            @else
                <p>{{ __('admin::deployment.no_log') }}</p>
            @endif
        </x-filament::section>
    </div>
</x-filament-panels::page>
