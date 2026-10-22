<div class="wanted-meta text-muted">
    <span>{{ $categories[$record->category_id] ?? '—' }}</span>
    <span>{{ $cities[$record->city_id] ?? __($record->copy().'.any_city') }}</span>
    <span>{{ __($record->copy().'.expires') }} <time datetime="{{ $record->expires_at->toIso8601String() }}">{{ \App\Support\PersianLocale::date($record->expires_at) }}</time></span>
</div>
@if($record->budget)<p class="wanted-budget">{{ __($record->copy().'.budget') }}: <bdi>{{ \App\Support\PersianLocale::number($record->budget) }}</bdi></p>@endif
@if($record->kind === 'service' && $record->preferred_time)<p>{{ __('listing::services.preferred_time') }}: <span dir="auto">{{ $record->preferred_time }}</span></p>@endif
