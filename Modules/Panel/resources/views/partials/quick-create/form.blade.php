@php
    $steps = [
        1 => __('panel::messages.photos'),
        2 => __('site::messages.category'),
        3 => __('site::messages.details'),
        4 => __('panel::messages.preview'),
    ];
@endphp

<div class="shell shell--narrow page quick-create">
    <div class="quick-create__topbar">
        <a href="{{ route('panel.listings.index') }}" class="icon-button" aria-label="{{ __('site::messages.back') }}"><x-ui.icon name="chevron-right"/></a>
        <strong>{{ __('site::messages.post_listing_cta') }}</strong>
        <a href="{{ route('panel.listings.index') }}" class="icon-button" aria-label="{{ __('panel::messages.cancel') }}"><x-ui.icon name="close"/></a>
    </div>
    <div class="stack stack--loose">
        <header class="stack stack--tight quick-create__heading">
            <p class="text-eyebrow">{{ __('panel::messages.new_listing') }}</p>
            <h1 class="title-page">{{ $this->currentStepTitle }}</h1>
            <p class="text-muted">{{ $this->currentStepHint }}</p>
        </header>

        <ol class="chip-row quick-create__steps" aria-label="{{ __('panel::messages.new_listing') }}">
            @foreach($steps as $number => $label)
                <li>
                    <button
                        type="button"
                        class="pill {{ $currentStep === $number ? 'is-active' : '' }}"
                        wire:click="goToStep({{ $number }})"
                        aria-current="{{ $currentStep === $number ? 'step' : 'false' }}"
                        @disabled($number > $currentStep)
                    >
                        <span>{{ $number }}</span>
                        <span>{{ $label }}</span>
                    </button>
                </li>
            @endforeach
        </ol>

        @if($publishError)
            <div class="alert alert--critical" role="alert">
                <x-ui.icon name="shield"/>
                <span>{{ $publishError }}</span>
            </div>
        @endif

        @error('photos')
            <div class="alert alert--critical" role="alert">{{ $message }}</div>
        @enderror
        @error('videos')
            <div class="alert alert--critical" role="alert">{{ $message }}</div>
        @enderror
        @error('videos.*')
            <div class="alert alert--critical" role="alert">{{ $message }}</div>
        @enderror
        @error('photos.*')
            <div class="alert alert--critical" role="alert"><x-ui.icon name="shield"/><span>{{ $message }}</span></div>
        @enderror

        <section class="card quick-create__card">
            <div class="card__body">
                @if($currentStep === 1)
                    <div class="quick-create__uploads">
                        <section class="quick-create__upload-group" aria-labelledby="quick-photos-label">
                            <h2 id="quick-photos-label" class="title-card">{{ __('panel::messages.photos') }}</h2>
                            <p class="field__hint" id="quick-photos-hint">{{ __('panel::messages.photos_hint', ['count' => config('quick-listing.max_photo_count', 20)]) }}</p>
                            <div class="quick-create__media-rail">
                                <label class="upload__control quick-create__add" for="quick-photos">
                                    <x-ui.icon name="image"/>
                                    <span>{{ __('panel::messages.add_photos') }}</span>
                                    <input id="quick-photos" type="file" class="visually-hidden" wire:model="photos" multiple accept="image/jpeg,image/png" aria-describedby="quick-photos-hint">
                                </label>
                                @foreach($photos as $index => $photo)
                                    <figure class="upload__preview quick-create__thumbnail" wire:key="quick-photo-{{ $photo->getFilename() }}">
                                        <img src="{{ $photo->temporaryUrl() }}" alt="{{ __('panel::messages.photos') }} {{ $index + 1 }}">
                                        <button type="button" class="icon-button quick-create__remove" wire:click="removePhoto({{ $index }})" aria-label="{{ __('favorite::messages.remove') }} {{ $index + 1 }}"><x-ui.icon name="close"/></button>
                                        @if($index === 0)<figcaption class="quick-create__cover">{{ __('panel::messages.cover_photo') }}</figcaption>@endif
                                    </figure>
                                @endforeach
                            </div>
                            <p wire:loading wire:target="photos" class="field__hint" role="status">{{ __('panel::messages.uploading') }}</p>
                        </section>
                        <section class="quick-create__upload-group" aria-labelledby="quick-videos-label">
                            <h2 id="quick-videos-label" class="title-card">{{ __('panel::messages.videos') }} <span class="text-muted">({{ __('panel::messages.optional') }})</span></h2>
                            @if(config('video.processing') === 'direct')
                                <p class="field__hint" id="quick-videos-hint">{{ __('video::admin.direct_mp4_only') }}</p>
                            @endif
                            <div class="quick-create__media-rail">
                                <label class="upload__control quick-create__add" for="quick-videos">
                                    <x-ui.icon name="video"/>
                                    <span>{{ __('panel::messages.add_videos') }}</span>
                                    <input id="quick-videos" type="file" class="visually-hidden" wire:model="videos" multiple accept="{{ config('video.processing') === 'direct' ? 'video/mp4' : 'video/*' }}" @if(config('video.processing') === 'direct') aria-describedby="quick-videos-hint" @endif>
                                </label>
                                @foreach($videos as $index => $video)
                                    <div class="quick-create__thumbnail quick-create__video" wire:key="quick-video-{{ $video->getFilename() }}">
                                        <x-ui.icon name="video"/>
                                        <span class="text-clamp-2" dir="auto">{{ $video->getClientOriginalName() }}</span>
                                        <button type="button" class="icon-button quick-create__remove" wire:click="removeVideo({{ $index }})" aria-label="{{ __('favorite::messages.remove') }} {{ $video->getClientOriginalName() }}"><x-ui.icon name="close"/></button>
                                    </div>
                                @endforeach
                            </div>
                            <p wire:loading wire:target="videos" class="field__hint" role="status">{{ __('panel::messages.uploading') }}</p>
                        </section>
                        <div class="quick-create__next-hint">
                            <x-ui.icon name="grid"/>
                            <span>{{ __('panel::messages.next') }}: {{ __('site::messages.category') }}</span>
                        </div>
                    </div>
                @elseif($currentStep === 2)
                    <div class="stack">
                        @if($detectedError)
                            <div class="alert alert--caution"><x-ui.icon name="sparkle"/><span>{{ $detectedError }}</span></div>
                        @elseif($detectedCategoryId)
                            <div class="alert alert--positive"><x-ui.icon name="sparkle"/><span>{{ $detectedReason }}</span></div>
                        @endif

                        <div class="field">
                            <label class="field__label" for="category-search">{{ __('site::messages.search') }}</label>
                            <span class="input-affix">
                                <x-ui.icon name="search" class="input-affix__icon"/>
                                <input id="category-search" type="search" class="input" wire:model.live.debounce.300ms="categorySearch">
                            </span>
                        </div>

                        @if($activeParentCategoryId)
                            <div class="row">
                                <button type="button" class="button button--ghost button--small" wire:click="backToRootCategories">
                                    <x-ui.icon name="arrow-left"/>
                                    <span>{{ __('site::messages.all_categories') }}</span>
                                </button>
                                <span class="text-muted">{{ $this->currentParentName }}</span>
                            </div>

                            <div class="nav-list">
                                @foreach($this->currentCategories as $category)
                                    <button
                                        type="button"
                                        class="nav-list__item {{ $selectedCategoryId === $category['id'] ? 'is-active' : '' }}"
                                        wire:click="selectCategory({{ $category['id'] }})"
                                    >
                                        <span>{{ \Modules\Category\Models\Category::localizedName($category['name']) }}</span>
                                        @if($selectedCategoryId === $category['id'])
                                            <x-ui.icon name="check"/>
                                        @else
                                            <x-ui.icon name="chevron-right"/>
                                        @endif
                                    </button>
                                @endforeach
                            </div>
                        @else
                            <div class="grid grid--categories">
                                @foreach($this->rootCategories as $category)
                                    <button type="button" class="category-card" wire:click="enterCategory({{ $category['id'] }})">
                                        <span class="category-card__icon"><x-ui.icon name="grid"/></span>
                                        <span class="category-card__name">{{ \Modules\Category\Models\Category::localizedName($category['name']) }}</span>
                                    </button>
                                @endforeach
                            </div>
                        @endif

                        @error('selectedCategoryId')<p class="field__error">{{ $message }}</p>@enderror
                    </div>
                @elseif($currentStep === 3)
                    <div class="stack">
                        <div class="field">
                            <label class="field__label" for="listing-title">{{ __('panel::messages.title') }}</label>
                            <input id="listing-title" dir="auto" type="text" class="input" wire:model.blur="listingTitle" maxlength="70">
                            @error('listingTitle')<p class="field__error">{{ $message }}</p>@enderror
                        </div>

                        <div class="field">
                            <label class="field__label" for="listing-description">{{ __('panel::messages.description') }}</label>
                            <textarea id="listing-description" dir="auto" class="textarea" rows="4" wire:model.blur="description" maxlength="1450"></textarea>
                            @error('description')<p class="field__error">{{ $message }}</p>@enderror
                        </div>

                        <div class="field__row field__row--three">
                            <div class="field">
                                <label class="field__label" for="listing-price">{{ __('panel::messages.price') }} ({{ \App\Support\Money::currencyLabel(\Modules\Listing\Support\ListingPanelHelper::defaultCurrency()) }})</label>
                                <input id="listing-price" type="text" inputmode="decimal" step="0.01" min="0" class="input" wire:model.blur="price">
                                @error('price')<p class="field__error">{{ $message }}</p>@enderror
                            </div>

                            <div class="field">
                                <label class="field__label" for="listing-country">{{ __('site::messages.country') }}</label>
                                <select id="listing-country" class="select" wire:model.live="selectedCountryId">
                                    <option value="">{{ __('site::messages.all_countries') }}</option>
                                    @foreach($countries as $country)
                                        <option value="{{ $country['id'] }}">{{ \Modules\Location\Models\Country::localizedName($country['name'], $country['code'] ?? '') }}</option>
                                    @endforeach
                                </select>
                                @error('selectedCountryId')<p class="field__error">{{ $message }}</p>@enderror
                            </div>

                            <div class="field">
                                <label class="field__label" for="listing-city">{{ __('site::messages.city') }}</label>
                                <select id="listing-city" class="select" wire:model="selectedCityId" @disabled($this->availableCities === [])>
                                    <option value="">{{ __('site::messages.all_cities') }}</option>
                                    @foreach($this->availableCities as $city)
                                        <option value="{{ $city['id'] }}">{{ $city['display_name'] ?? $city['name'] }}</option>
                                    @endforeach
                                </select>
                                @error('selectedCityId')<p class="field__error">{{ $message }}</p>@enderror
                            </div>
                        </div>

                        @if($listingCustomFields !== [])
                            <div class="field-set">
                                <p class="field-set__legend">{{ __('site::messages.details') }}</p>
                                @foreach($listingCustomFields as $field)
                                    <div class="field">
                                        <label class="field__label" for="custom-{{ $field['name'] }}">{{ __('listing::messages.field_labels')[$field['label']] ?? $field['label'] }}</label>
                                        @if(($field['type'] ?? 'text') === 'select')
                                            <select id="custom-{{ $field['name'] }}" class="select" wire:model="customFieldValues.{{ $field['name'] }}">
                                                <option value="">—</option>
                                                @foreach($field['options'] ?? [] as $option)
                                                    <option value="{{ $option }}">{{ __('listing::messages.field_options')[$option] ?? $option }}</option>
                                                @endforeach
                                            </select>
                                        @elseif(($field['type'] ?? 'text') === 'boolean')
                                            <label class="checkbox">
                                                <input type="checkbox" id="custom-{{ $field['name'] }}" wire:model="customFieldValues.{{ $field['name'] }}">
                                                <span>{{ __('listing::messages.field_labels')[$field['label']] ?? $field['label'] }}</span>
                                            </label>
                                        @elseif(($field['type'] ?? 'text') === 'textarea')
                                            <textarea id="custom-{{ $field['name'] }}" class="textarea" rows="3" wire:model="customFieldValues.{{ $field['name'] }}"></textarea>
                                        @else
                                            <input
                                                id="custom-{{ $field['name'] }}"
                                                type="text" @if(($field['type'] ?? 'text') === 'number') inputmode="decimal" @endif
                                                class="input"
                                                wire:model="customFieldValues.{{ $field['name'] }}"
                                            >
                                        @endif
                                        @if(filled($field['help_text'] ?? null))
                                            <p class="field__hint">{{ $field['help_text'] }}</p>
                                        @endif
                                        @error('customFieldValues.'.$field['name'])<p class="field__error">{{ $message }}</p>@enderror
                                    </div>
                                @endforeach
                            </div>
                        @endif
                    </div>
                @else
                    <div class="stack">
                        <dl class="spec-list">
                            <div class="spec-list__row">
                                <dt class="spec-list__label">{{ __('panel::messages.title') }}</dt>
                                <dd class="spec-list__value" dir="auto">{{ $listingTitle }}</dd>
                            </div>
                            <div class="spec-list__row">
                                <dt class="spec-list__label">{{ __('site::messages.category') }}</dt>
                                <dd class="spec-list__value" dir="auto">{{ $this->selectedCategoryPath }}</dd>
                            </div>
                            <div class="spec-list__row">
                                <dt class="spec-list__label">{{ __('panel::messages.price') }}</dt>
                                <dd class="spec-list__value" dir="auto">{{ \App\Support\Money::valid($price, \Modules\Listing\Support\ListingPanelHelper::defaultCurrency()) ? \App\Support\Money::format($price, \Modules\Listing\Support\ListingPanelHelper::defaultCurrency()) : $price }}</dd>
                            </div>
                            <div class="spec-list__row">
                                <dt class="spec-list__label">{{ __('panel::messages.location') }}</dt>
                                <dd class="spec-list__value" dir="auto">{{ $this->selectedCityDisplayName }} {{ $this->selectedCountryDisplayName }}</dd>
                            </div>
                        </dl>

                        @if(filled($description))
                            <div class="prose" dir="auto">{{ $description }}</div>
                        @endif

                        @if($photos !== [])
                            <div class="upload__grid">
                                @foreach($photos as $photo)
                                    <figure class="upload__preview"><img src="{{ $photo->temporaryUrl() }}" alt=""></figure>
                                @endforeach
                            </div>
                        @endif
                    </div>
                @endif
            </div>

            <div class="card__foot quick-create__actions">
                <div class="row row--between">
                    @if($currentStep > 1)
                        <button type="button" class="button button--ghost" wire:click="goToStep({{ $currentStep - 1 }})">
                            <x-ui.icon name="arrow-left"/>
                            <span>{{ __('site::messages.back') }}</span>
                        </button>
                    @else
                        <a href="{{ route('panel.listings.index') }}" class="button button--ghost">{{ __('panel::messages.cancel') }}</a>
                    @endif

                    @if($currentStep === 1)
                        <button type="button" class="button button--primary" wire:click="goToCategoryStep" wire:loading.attr="disabled">{{ __('panel::messages.next') }}</button>
                    @elseif($currentStep === 2)
                        <button type="button" class="button button--primary" wire:click="goToDetailsStep" wire:loading.attr="disabled">{{ __('panel::messages.next') }}</button>
                    @elseif($currentStep === 3)
                        <button type="button" class="button button--primary" wire:click="goToPreviewStep" wire:loading.attr="disabled">{{ __('panel::messages.next') }}</button>
                    @else
                        <button type="button" class="button button--primary button--large" wire:click="publishListing" wire:loading.attr="disabled">
                            <span wire:loading.remove wire:target="publishListing">{{ __('site::messages.post_listing_cta') }}</span>
                            <span wire:loading wire:target="publishListing">{{ __('panel::messages.publishing') }}</span>
                        </button>
                    @endif
                </div>
            </div>
        </section>
    </div>
</div>
