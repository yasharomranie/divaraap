<form action="{{ route('listings.index') }}" method="GET" class="mobile-market-search" role="search" data-location-search>
    <label for="mobile-market-query" class="visually-hidden">{{ __('site::messages.search') }}</label>
    <button type="submit" class="mobile-market-search__submit" aria-label="{{ __('site::messages.search') }}"><x-ui.icon name="search"/></button>
    <input id="mobile-market-query" class="site-search__input" type="search" name="search" value="{{ request('search') }}" placeholder="{{ __('site::home.search_hint') }}" enterkeyhint="search" maxlength="120" @if(request()->routeIs('listings.index') && \App\Support\PersianSearch::terms((string) request('search', '')) === null) aria-invalid="true" aria-describedby="search-error" @endif>
    <details class="mobile-market-filter">
        <summary aria-label="{{ __('site::home.filter_listings') }}"><x-ui.icon name="sliders"/></summary>
        <div class="mobile-market-filter__panel">
            <label class="field__label" for="mobile-filter-category">{{ __('site::messages.categories') }}</label>
            <select id="mobile-filter-category" class="select" name="category">
                <option value="">{{ __('site::messages.all_categories') }}</option>
                @foreach($navCategories as $navCategory)<option value="{{ $navCategory['id'] }}" @selected((string) request('category') === (string) $navCategory['id'])>{{ \Modules\Category\Models\Category::localizedName($navCategory['name']) }}</option>@endforeach
            </select>
            <label class="field__label" for="mobile-filter-sort">{{ __('site::mobile.sort') }}</label>
            <select id="mobile-filter-sort" class="select" name="sort">
                @foreach(['newest', 'oldest', 'price_asc', 'price_desc'] as $sort)<option value="{{ $sort }}" @selected(request('sort', 'newest') === $sort)>{{ __('site::messages.sort_'.$sort) }}</option>@endforeach
            </select>
            <button type="submit" class="button button--accent button--block">{{ __('site::messages.apply') }}</button>
        </div>
    </details>
</form>
