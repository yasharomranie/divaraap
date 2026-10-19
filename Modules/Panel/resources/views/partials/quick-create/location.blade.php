<details class="quick-create-location" data-create-location wire:key="create-location" @if($errors->has('latitude') || $errors->has('longitude')) open @endif
    data-loading="{{ __('listing::map.loading') }}" data-failed="{{ __('listing::map.editor_failed') }}"
    data-ready="{{ __('listing::map.pick_ready') }}" data-selected="{{ __('listing::map.location_selected') }}"
    data-empty="{{ __('listing::map.location_empty') }}" data-locating="{{ __('listing::map.locating') }}"
    data-geolocation-failed="{{ __('listing::map.geolocation_failed') }}"
    data-zoom-in="{{ __('listing::map.zoom_in') }}" data-zoom-out="{{ __('listing::map.zoom_out') }}">
    <summary>{{ __('listing::map.optional_location') }}</summary>
    <div class="stack stack--tight">
        <p class="text-muted">{{ __('listing::map.pick_hint') }}</p>
        <div wire:ignore>
            <div class="listing-map-canvas" data-create-location-canvas role="region" aria-label="{{ __('listing::map.region') }}"></div>
            <p role="status" data-create-location-status></p>
            <div class="quick-create-location__actions">
                <button type="button" class="button button--ghost" data-create-location-current hidden>{{ __('listing::map.current_location') }}</button>
                <button type="button" class="button button--ghost" data-create-location-clear hidden>{{ __('listing::map.remove_location') }}</button>
            </div>
        </div>
        <div class="field__row">
            <div class="field">
                <label class="field__label" for="create-latitude">{{ __('listing::map.latitude') }}</label>
                <input id="create-latitude" class="input" type="number" step="any" min="-85.05112878" max="85.05112878" dir="ltr" wire:model="latitude">
                @error('latitude')<p class="field__error" role="alert">{{ $message }}</p>@enderror
            </div>
            <div class="field">
                <label class="field__label" for="create-longitude">{{ __('listing::map.longitude') }}</label>
                <input id="create-longitude" class="input" type="number" step="any" min="-180" max="180" dir="ltr" wire:model="longitude">
                @error('longitude')<p class="field__error" role="alert">{{ $message }}</p>@enderror
            </div>
        </div>
    </div>
</details>
