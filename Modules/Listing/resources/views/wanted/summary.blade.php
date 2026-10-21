<div class="wanted-meta text-muted">
    <span>{{ $categories[$record->category_id] ?? '—' }}</span>
    <span>{{ $cities[$record->city_id] ?? __('listing::wanted.any_city') }}</span>
    <span>{{ __('listing::wanted.expires') }} <time datetime="{{ $record->expires_at->toIso8601String() }}">{{ \App\Support\PersianLocale::date($record->expires_at) }}</time></span>
</div>
@if($record->budget)<p class="wanted-budget">{{ __('listing::wanted.budget') }}: <bdi>{{ \App\Support\PersianLocale::number($record->budget) }}</bdi></p>@endif
