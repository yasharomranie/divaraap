import { listingBulk } from './modules/listing-bulk';
import { listingTextSuggestions } from './modules/listing-text-suggestions';
import { listingCreateLocation } from './modules/listing-create-location';
import '../css/app.css';
import { listingLocation } from './modules/listing-location';
import { listingMap } from './modules/listing-map';
import { advertisingMetrics } from './modules/advertising-metrics';

import { startBehaviors, type Behavior } from './core/behavior';
import { characterCounter, confirmAction, dependentSelect, imagePreview, ratingInput, revealPanel } from './modules/forms';
import { favoriteToggle } from './modules/favorite-toggle';
import { filterDrawer, listingFilters, viewModeToggle } from './modules/listing-filters';
import { chatOffers, inboxBadge, inboxPane, inboxThread } from './modules/inbox';
import { homeShowcase } from './modules/home-showcase';
import { listingGallery } from './modules/gallery';
import { locationPicker } from './modules/location-picker';
import { contactReveal, shareAction } from './modules/reveal';
import { appNavigation, disclosureGroup, navigationDrawer, searchSuggest, stickyHeader } from './modules/navigation';

const behaviors: readonly Behavior<never>[] = [
    listingBulk,
    listingTextSuggestions,
    listingCreateLocation,
    listingMap,
    listingLocation,
    advertisingMetrics,
    homeShowcase,
    navigationDrawer,
    appNavigation,
    disclosureGroup,
    stickyHeader,
    searchSuggest,
    locationPicker,
    listingFilters,
    filterDrawer,
    viewModeToggle,
    listingGallery,
    favoriteToggle,
    contactReveal,
    shareAction,
    inboxThread,
    chatOffers,
    inboxPane,
    inboxBadge,
    characterCounter,
    dependentSelect,
    imagePreview,
    confirmAction,
    ratingInput,
    revealPanel,
];

function boot(): void {
    startBehaviors(behaviors);
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', boot, { once: true });
} else {
    boot();
}

document.addEventListener('livewire:navigated', boot);

document.addEventListener('toggle', event => {
    const target = event.target;
    if (target instanceof HTMLDetailsElement && target.parentElement) {
        if (target.matches('[data-create-location]')) startBehaviors([listingCreateLocation], target.parentElement);
        if (target.matches('[data-listing-text-suggestions]')) startBehaviors([listingTextSuggestions], target.parentElement);
    }
}, true);
