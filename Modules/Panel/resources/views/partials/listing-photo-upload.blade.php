<section id="listing-images" class="card listing-photo-upload" tabindex="-1" aria-labelledby="listing-images-heading">
    <div class="card__head"><h2 class="card__title" id="listing-images-heading">{{ __('listing::improvement.current_photos') }}</h2></div>
    <div class="card__body stack">
        @php $gallery = $listing->galleryImageData(); @endphp
        @if($gallery !== [])
            <div class="listing-photo-upload__grid">
                @foreach($gallery as $image)
                    <img src="{{ \Modules\Listing\Support\ListingImageViewData::pickUrl($image['thumb']) }}" alt="{{ __('listing::improvement.photo_alt') }}" loading="lazy" width="96" height="96">
                @endforeach
            </div>
        @else
            <p>{{ __('listing::improvement.no_photos') }}</p>
        @endif
        @if(count($gallery) < (int) config('quick-listing.max_photo_count', 20))
            <form method="POST" action="{{ route('panel.listings.photos.store', $listing) }}" enctype="multipart/form-data" class="stack stack--tight">
                @csrf
                <label class="field__label" for="listing-photo">{{ __('listing::improvement.photo_label') }}</label>
                <input class="input" id="listing-photo" type="file" name="photo" accept="image/jpeg,image/png" required aria-describedby="listing-photo-hint">
                <p class="field__hint" id="listing-photo-hint">{{ __('listing::improvement.photo_hint', ['size' => \App\Support\PersianLocale::number(config('quick-listing.max_photo_size_kb', 5120) / 1024), 'count' => \App\Support\PersianLocale::number(config('quick-listing.max_photo_count', 20))]) }}</p>
                @error('photo')<p class="field__error" role="alert">{{ $message }}</p>@enderror
                <button class="button button--secondary" type="submit">{{ __('listing::improvement.photo_upload') }}</button>
            </form>
        @else
            <p>{{ __('listing::improvement.photo_limit') }}</p>
        @endif
    </div>
</section>
