@if($sellerAvailability ?? null)
<aside class="seller-availability {{ ($compactAvailability ?? false) ? 'seller-availability--compact' : '' }}" data-seller-availability data-expires="{{ $sellerAvailability['until']->toIso8601String() }}" role="note" aria-label="{{ __('user::availability.public_title') }}">
    <strong>{{ __('user::availability.'.$sellerAvailability['reason']) }}</strong>
    <p class="seller-availability__hint">{{ __('user::availability.notice') }}</p>
    <p>{{ __('user::availability.until_label') }} <time datetime="{{ $sellerAvailability['until']->toIso8601String() }}">{{ \App\Support\PersianLocale::date($sellerAvailability['until'], true) }}</time> · {{ __('user::availability.timezone') }}</p>
</aside>
@endif
