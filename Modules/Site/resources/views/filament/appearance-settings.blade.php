<x-filament-panels::page>
    @php
        $draft = \App\Support\AppearanceTheme::normalize($this->data ?? []);
    @endphp
    <section aria-label="{{ __('site::appearance.preview') }}" class="appearance-preview-section">
        <h2>{{ __('site::appearance.preview') }}</h2>
        <p>{{ __('site::appearance.preview_hint') }}</p>
        <label for="appearance-preview-device">{{ __('site::appearance.preview') }}
            <select id="appearance-preview-device" wire:model.live="previewDevice" style="min-height:44px;padding:.5rem;border:1px solid #999;border-radius:.5rem;color:inherit;background:transparent">
                <option value="mobile">{{ __('site::appearance.mobile') }}</option>
                <option value="desktop">{{ __('site::appearance.desktop') }}</option>
            </select>
        </label>
        <style>
            .appearance-preview-section { min-width: 0; }
            .appearance-preview-section > p { margin-block: .5rem 1rem; }
            .appearance-preview { border: 1px solid var(--appearance-border); border-radius: .75rem; overflow: hidden; background: var(--appearance-page); color: var(--appearance-text); font-family: Vazirmatn, sans-serif; line-height: 1.75; overflow-wrap: anywhere; font-size: var(--font-body); }
            .appearance-preview header { padding: 1rem; background: var(--appearance-header); color: var(--appearance-header-text); font-size: var(--font-brand); }
            .appearance-preview main { padding: 1.25rem; }
            .appearance-preview h3 { font-size: var(--font-page-title); color: var(--appearance-heading); }
            .appearance-preview article { padding: 1rem; margin-block: 1rem; background: var(--appearance-surface); border-radius: .5rem; }
            .appearance-preview h4 { font-size: var(--font-listing-title); color: var(--appearance-heading); }
            .appearance-preview .sample-price { font-size: var(--font-listing-price); color: var(--appearance-price); }
            .appearance-preview .sample-button { display: inline-block; padding: .6rem 1rem; margin-block: .75rem; background: var(--appearance-primary); color: var(--appearance-on-primary); border-radius: .5rem; font-size: var(--font-button); }
            .appearance-preview .sample-link { color: var(--appearance-accent); font-size: var(--font-navigation); }
            .appearance-preview footer { padding: 1rem; background: var(--appearance-footer); color: var(--appearance-footer-text); font-size: var(--font-footer-link); }
        </style>
        <div class="appearance-preview" style="{{ \App\Support\AppearanceTheme::variables($draft, $this->previewDevice) }}max-width:{{ $this->previewDevice === 'desktop' ? '100%' : '390px' }};margin-block-start:1rem">
            <header>{{ __('site::appearance.preview_title') }}</header>
            <main>
                <h3>{{ __('site::appearance.preview_title') }}</h3>
                <p>{{ __('site::appearance.preview_text') }}</p>
                <article>
                    <h4>{{ __('site::appearance.preview_listing') }}</h4>
                    <p class="sample-price">{{ __('site::appearance.preview_price') }}</p>
                    <span class="sample-button">{{ __('site::appearance.preview_button') }}</span>
                </article>
                <span class="sample-link">{{ __('site::appearance.preview_link') }}</span>
            </main>
            <footer>{{ __('site::appearance.preview_footer') }}</footer>
        </div>
    </section>
    {{ $this->content }}
</x-filament-panels::page>
