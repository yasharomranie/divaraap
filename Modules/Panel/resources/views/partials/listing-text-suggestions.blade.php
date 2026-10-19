<details id="listing-text-suggestions" class="listing-text-tools" data-listing-text-suggestions wire:ignore.self
    data-endpoint="{{ route('panel.listings.text-suggestions') }}" data-title-id="{{ $suggestionTitleId }}"
    data-description-id="{{ $suggestionDescriptionId }}" data-city-id="{{ $suggestionCityId }}" data-category-id="{{ $suggestionCategoryId }}"
    data-loading="{{ __('listing::suggestions.loading') }}" data-failed="{{ __('listing::suggestions.failed') }}"
    data-empty="{{ __('listing::suggestions.empty') }}" data-ready="{{ __('listing::suggestions.ready') }}"
    data-applied="{{ __('listing::suggestions.applied') }}" data-restored="{{ __('listing::suggestions.restored') }}"
    data-stale="{{ __('listing::suggestions.stale') }}" data-title-label="{{ __('listing::suggestions.title_label') }}"
    data-description-label="{{ __('listing::suggestions.description_label') }}" data-use-title="{{ __('listing::suggestions.use_title') }}"
    data-use-description="{{ __('listing::suggestions.use_description') }}"
    data-concise="{{ __('listing::suggestions.concise') }}" data-category="{{ __('listing::suggestions.category') }}"
    data-city="{{ __('listing::suggestions.city') }}" data-tidy="{{ __('listing::suggestions.tidy') }}" data-structured="{{ __('listing::suggestions.structured') }}">
    <summary>{{ __('listing::suggestions.title') }}</summary>
    <div class="stack stack--tight" wire:ignore>
        <p class="text-muted">{{ __('listing::suggestions.hint') }}</p>
        <p class="text-muted">{{ __('listing::suggestions.review') }}</p>
        <button class="button button--secondary" type="button" data-suggest-generate hidden>{{ __('listing::suggestions.generate') }}</button>
        <p role="status" data-suggest-status></p>
        <div class="listing-text-tools__results" data-suggest-results></div>
        <button class="button button--ghost" type="button" data-suggest-undo hidden>{{ __('listing::suggestions.undo') }}</button>
        <noscript><p>{{ __('listing::suggestions.no_js') }}</p></noscript>
    </div>
</details>
