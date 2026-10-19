<?php

declare(strict_types=1);

namespace Modules\Listing\Models;

use App\Support\Money;
use App\Support\PersianLocale;
use App\Support\PersianSearch;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Modules\Category\Models\Category;
use Modules\Conversation\App\Models\Conversation;
use Modules\Favorite\App\Models\FavoriteListing;
use Modules\Listing\States\ActiveListingStatus;
use Modules\Listing\States\ExpiredListingStatus;
use Modules\Listing\States\ListingStatus;
use Modules\Listing\States\PendingListingStatus;
use Modules\Listing\States\RejectedListingStatus;
use Modules\Listing\States\SoldListingStatus;
use Modules\Listing\Support\ListingImageViewData;
use Modules\Listing\Support\ListingImprovementGuide;
use Modules\Listing\Support\ListingPanelHelper;
use Modules\Location\Models\Country;
use Modules\Location\Support\IranianCities;
use Modules\Site\App\Support\LocalMedia;
use Modules\User\App\Models\User;
use Modules\Video\Models\Video;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;
use Spatie\Image\Enums\Fit;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Spatie\ModelStates\HasStates;
use Throwable;

class Listing extends Model implements HasMedia
{
    use HasFactory, HasStates, InteractsWithMedia, LogsActivity, SoftDeletes;

    private const DEFAULT_PANEL_EXPIRY_WINDOW_DAYS = 30;

    protected $fillable = [
        'title', 'description', 'price', 'currency', 'category_id',
        'images', 'custom_fields',
        'contact_phone', 'contact_email', 'expires_at',
        'city', 'country', 'latitude', 'longitude', 'location', 'view_count',
    ];

    protected $casts = [
        'images' => 'array',
        'custom_fields' => 'array',
        'is_featured' => 'boolean',
        'view_count' => 'integer',
        'expires_at' => 'datetime',
        'price' => 'decimal:2',
        'latitude' => 'decimal:7',
        'longitude' => 'decimal:7',
        'status' => ListingStatus::class,
    ];

    protected $appends = ['location'];

    public static function advertisingDestinationId(string $url): ?int
    {
        $destination = parse_url($url);
        $origin = parse_url((string) config('app.url'));
        if (! is_array($destination) || ! is_array($origin) || strtolower($destination['host'] ?? '') !== strtolower($origin['host'] ?? '') || ($destination['scheme'] ?? '') !== ($origin['scheme'] ?? '') || ($destination['port'] ?? null) !== ($origin['port'] ?? null) || ! preg_match('~^/listings/([1-9][0-9]*)/?$~', $destination['path'] ?? '', $match)) {
            return null;
        }

        return static::query()->where('status', 'active')->whereKey($match[1])->value('id');
    }

    public static function moderationQueue(): Collection
    {
        return static::query()->where('status', 'pending')->with('media')->oldest()->limit(20)->get(['id', 'title', 'description', 'images', 'category_id', 'created_at']);
    }

    public function moderate(bool $approve, ?string $note = null): void
    {
        if ($this->statusValue() !== 'pending') {
            return;
        }
        $this->status->transitionTo($approve ? ActiveListingStatus::class : RejectedListingStatus::class);
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logFillable()
            ->logOnlyDirty()
            ->dontLogEmptyChanges();
    }

    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function favoritedByUsers()
    {
        return $this->belongsToMany(User::class, 'favorite_listings')
            ->using(FavoriteListing::class)
            ->wherePivotNull('deleted_at')
            ->withTimestamps();
    }

    public function conversations()
    {
        return $this->hasMany(Conversation::class);
    }

    public function videos()
    {
        return $this->hasMany(Video::class)->ordered();
    }

    public function updateMapLocationFor(User $user, ?float $latitude, ?float $longitude): void
    {
        DB::transaction(function () use ($user, $latitude, $longitude): void {
            $locked = self::whereKey($this->id)->lockForUpdate()->firstOrFail();
            $locked->assertOwnedBy($user);
            $locked->forceFill(['latitude' => $latitude, 'longitude' => $longitude])->save();
        });
    }

    public static function mapResults(Builder $query, ?array $bounds): array
    {
        $query = clone $query;
        $query->active()->whereBetween('latitude', [-85.05112878, 85.05112878])->whereBetween('longitude', [-180, 180])
            ->where(fn (Builder $active) => $active->whereNull('expires_at')->orWhere('expires_at', '>', now()));
        if ($bounds !== null) {
            $query->whereRaw('ROUND(latitude, 3) BETWEEN ? AND ?', [$bounds['south'], $bounds['north']]);
            if ($bounds['west'] < $bounds['east']) {
                $query->whereRaw('ROUND(longitude, 3) BETWEEN ? AND ?', [$bounds['west'], $bounds['east']]);
            } else {
                $query->where(fn (Builder $longitude) => $longitude->whereRaw('ROUND(longitude, 3) >= ?', [$bounds['west']])->orWhereRaw('ROUND(longitude, 3) <= ?', [$bounds['east']]));
            }
        }
        $total = (clone $query)->count();
        $listings = $query->orderBy('id')->limit(200)->get(['id', 'title', 'price', 'currency', 'latitude', 'longitude', 'category_id', 'city', 'country']);
        $points = $listings->map(fn (self $listing) => [
            'id' => $listing->id, 'title' => $listing->title, 'price' => $listing->panelPriceLabel(),
            'lat' => round((float) $listing->latitude, 3), 'lng' => round((float) $listing->longitude, 3),
            'url' => route('listings.show', $listing->id),
        ])->all();

        return compact('total', 'listings', 'points');
    }

    public function comparisonPlaceLabel(): string
    {
        $country = trim((string) $this->country);
        $city = trim((string) $this->city);
        if (in_array($country, ['Iran', 'IR', 'ایران'], true)) {
            $city = IranianCities::label($city);
            $country = Country::localizedName('Iran', 'IR');
        }

        return collect([$city, $country])->filter()->implode(' · ') ?: '—';
    }

    public static function comparisonListings(array $ids): Collection
    {
        return self::query()->active()->whereIn('id', $ids)->with('category:id,name')
            ->where(fn (Builder $query) => $query->whereNull('expires_at')->orWhere('expires_at', '>', now()))->get();
    }

    public static function buyerAlertListings(array $ids, array $filters = [], ?int $excludeUser = null): Collection
    {
        $directory = ! empty($filters['category']) ? Category::listingDirectory((int) $filters['category']) : null;
        if ($directory !== null && ! $directory['selectedCategory']) {
            return collect();
        }
        $category = $directory['filterIds'] ?? null;
        $location = ! empty($filters['country']) || ! empty($filters['city'])
            ? Country::browseSelection(! empty($filters['country']) ? (int) $filters['country'] : null, ! empty($filters['city']) ? (int) $filters['city'] : null)
            : ['country_id' => null, 'city_id' => null, 'selected_country_name' => null, 'selected_city_name' => null];
        if ((! empty($filters['country']) && $location['country_id'] !== (int) $filters['country']) || (! empty($filters['city']) && $location['city_id'] !== (int) $filters['city'])) {
            return collect();
        }

        return self::query()->active()->whereIn('id', $ids)
            ->where(fn (Builder $query) => $query->whereNull('expires_at')->orWhere('expires_at', '>', now()))
            ->when($excludeUser, fn (Builder $query) => $query->where('user_id', '!=', $excludeUser))
            ->forBrowseFilters([
                'search' => $filters['search'] ?? '', 'category_ids' => $category,
                'user_id' => $filters['seller'] ?? $filters['user'] ?? null,
                'country' => $location['selected_country_name'], 'city' => $location['selected_city_name'],
                'min_price' => $filters['min_price'] ?? null, 'max_price' => $filters['max_price'] ?? null,
            ])->get();
    }

    public function scopePublicFeed(Builder $query): Builder
    {
        return $query
            ->active()
            ->orderByDesc('is_featured')
            ->orderByDesc('created_at');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', 'active');
    }

    public function scopeOwnedByUser(Builder $query, int|string|null $userId): Builder
    {
        return $query->where('user_id', $userId);
    }

    public function scopeForPanelStatus(Builder $query, string $status): Builder
    {
        return match ($status) {
            'sold', 'expired', 'pending', 'active' => $query->where('status', $status),
            default => $query,
        };
    }

    public function scopeSearchTerm(Builder $query, string $search): Builder
    {
        $terms = PersianSearch::terms($search);
        if ($terms === null) {
            return $query->whereRaw('1 = 0');
        }
        if ($terms === []) {
            return $query;
        }
        $grammar = $query->getQuery()->getGrammar();
        $columns = array_map(fn (string $column) => PersianSearch::sql($grammar->wrap($this->qualifyColumn($column))), ['title', 'description', 'city', 'country']);
        foreach ($terms as $term) {
            $query->where(function (Builder $match) use ($columns, $term): void {
                foreach ($columns as $column) {
                    $match->orWhereRaw($column." LIKE ? ESCAPE '!'", [PersianSearch::pattern($term)]);
                }
            });
        }

        return $query;
    }

    public function scopeWithPanelIndexState(Builder $query): Builder
    {
        return $query->with('category:id,name');
    }

    public function scopeForCategory(Builder $query, ?int $categoryId): Builder
    {
        return $query->forCategoryIds(Category::listingFilterIds($categoryId));
    }

    public function scopeForCategoryIds(Builder $query, ?array $categoryIds): Builder
    {
        if ($categoryIds === null) {
            return $query;
        }

        if ($categoryIds === []) {
            return $query->whereRaw('1 = 0');
        }

        return $query->whereIn('category_id', $categoryIds);
    }

    public function scopeForBrowseFilters(Builder $query, array $filters): Builder
    {
        $search = trim((string) ($filters['search'] ?? ''));
        $country = isset($filters['country']) ? trim((string) $filters['country']) : null;
        $city = isset($filters['city']) ? trim((string) $filters['city']) : null;
        $userId = isset($filters['user_id']) && is_numeric($filters['user_id']) ? (int) $filters['user_id'] : null;
        $minPrice = Money::valid($filters['min_price'] ?? null, 'IRR') ? Money::decimal($filters['min_price']) : null;
        $maxPrice = Money::valid($filters['max_price'] ?? null, 'IRR') ? Money::decimal($filters['max_price']) : null;
        $dateFilter = (string) ($filters['date_filter'] ?? 'all');
        $categoryIds = $filters['category_ids'] ?? null;

        $query
            ->searchTerm($search)
            ->forCategoryIds(is_array($categoryIds) ? $categoryIds : null)
            ->when(! is_null($userId) && $userId > 0, fn (Builder $builder) => $builder->where('user_id', $userId))
            ->when($country !== null && $country !== '', fn (Builder $builder) => $builder->where('country', $country))
            ->when($city !== null && $city !== '', fn (Builder $builder) => $builder->where('city', $city))
            ->when(! is_null($minPrice), fn (Builder $builder) => $builder->where('currency', 'IRR')->whereNotNull('price')->where('price', '>=', $minPrice))
            ->when(! is_null($maxPrice), fn (Builder $builder) => $builder->where('currency', 'IRR')->whereNotNull('price')->where('price', '<=', $maxPrice));

        return match ($dateFilter) {
            'today' => $query->where('created_at', '>=', Carbon::now()->startOfDay()),
            'week' => $query->where('created_at', '>=', Carbon::now()->subDays(7)),
            'month' => $query->where('created_at', '>=', Carbon::now()->subDays(30)),
            default => $query,
        };
    }

    public function scopeApplyBrowseSort(Builder $query, string $sort): Builder
    {
        return match ($sort) {
            'newest' => $query->reorder()->orderByDesc('created_at'),
            'oldest' => $query->reorder()->orderBy('created_at'),
            'price_asc' => $query->where('currency', 'IRR')->reorder()->orderByRaw('price is null')->orderBy('price'),
            'price_desc' => $query->where('currency', 'IRR')->reorder()->orderByRaw('price is null')->orderByDesc('price'),
            default => $query->reorder()->orderByDesc('is_featured')->orderByDesc('created_at'),
        };
    }

    public function themeGallery(): array
    {
        return collect($this->galleryImageData())
            ->map(fn (array $image): ?string => ListingImageViewData::pickUrl($image['gallery'] ?? null))
            ->filter(fn (?string $url): bool => is_string($url) && $url !== '')
            ->values()
            ->all();
    }

    public function galleryImageData(): array
    {
        $media = $this->getMedia('listing-images')->map(fn (Media $media): array => [
            'gallery' => ListingImageViewData::fromMedia($media, 'gallery'),
            'thumb' => ListingImageViewData::fromMedia($media, 'thumb'),
        ]);
        $legacy = collect($this->images ?? [])
            ->filter(fn ($value): bool => is_string($value) && trim($value) !== '')
            ->map(fn (string $url): array => [
                'gallery' => ListingImageViewData::fromUrl($url),
                'thumb' => ListingImageViewData::fromUrl($url),
            ]);

        return $media->concat($legacy)->unique(fn (array $image): ?string => $image['gallery']['fallback'] ?? null)->values()->all();
    }

    public function improvementGuide(): array
    {
        $this->loadMissing('media');

        return ListingImprovementGuide::evaluate($this->getAttributes(), count($this->galleryImageData()));
    }

    public function addPanelPhoto(User $user, UploadedFile $photo): void
    {
        DB::transaction(function () use ($user, $photo): void {
            $locked = static::query()->lockForUpdate()->findOrFail($this->getKey());
            $locked->assertOwnedBy($user);
            abort_unless((string) $user->status === 'active', 403);
            if (count($locked->galleryImageData()) >= (int) config('quick-listing.max_photo_count', 20)) {
                throw ValidationException::withMessages(['photo' => __('listing::improvement.photo_limit')]);
            }
            $locked->attachListingImage($photo->getRealPath(), (string) Str::uuid().'.'.$photo->extension(), LocalMedia::disk());
        });
        $this->unsetRelation('media');
    }

    public function primaryImageData(string $context = 'card'): ?array
    {
        $media = $this->getFirstMedia('listing-images');

        if ($media instanceof Media) {
            return ListingImageViewData::fromMedia($media, $context);
        }

        $fallback = collect($this->images ?? [])
            ->first(fn ($value): bool => is_string($value) && trim($value) !== '');

        return ListingImageViewData::fromUrl(is_string($fallback) ? $fallback : null);
    }

    public function primaryImageUrl(string $context = 'card', string $viewport = 'desktop'): ?string
    {
        return ListingImageViewData::pickUrl($this->primaryImageData($context), $viewport);
    }

    public function relatedSuggestions(int $limit = 8): Collection
    {
        $limit = max(0, min(12, $limit));
        $groups = Category::recommendationGroups($this->category_id ? (int) $this->category_id : null);
        if ($limit === 0 || $groups['family'] === []) {
            return collect();
        }
        $query = static::query()->active()->whereKeyNot($this->getKey())
            ->where(fn (Builder $query) => $query->whereNull('expires_at')->orWhere('expires_at', '>', now()))
            ->whereIn('category_id', $groups['family'])
            ->with(['category:id,name', 'media']);
        $countryNames = $this->recommendationCountryNames();
        if ($countryNames !== []) {
            $query->whereIn(DB::raw("LOWER(TRIM(COALESCE(country, '')))"), $countryNames);
        }
        $placeholders = implode(',', array_fill(0, count($groups['primary']), '?'));
        $query->orderByRaw("CASE WHEN category_id = ? THEN 0 WHEN category_id IN ({$placeholders}) THEN 1 ELSE 2 END", [$this->category_id, ...$groups['primary']]);
        $city = mb_strtolower(trim((string) $this->city));
        if ($countryNames !== [] && $city !== '' && $city !== 'other') {
            $query->orderByRaw("CASE WHEN LOWER(TRIM(COALESCE(city, ''))) = ? THEN 0 ELSE 1 END", [$city]);
        }
        $currency = strtoupper(trim((string) $this->currency));
        if ((float) $this->price > 0 && $currency !== '') {
            $query->orderByRaw("CASE WHEN UPPER(TRIM(COALESCE(currency, ''))) = ? AND price > 0 THEN 0 ELSE 1 END", [$currency])
                ->orderByRaw("CASE WHEN UPPER(TRIM(COALESCE(currency, ''))) = ? AND price > 0 THEN ABS(price - ?) ELSE NULL END ASC", [$currency, $this->price]);
        }

        return $query->orderByDesc('created_at')->orderByDesc('id')->limit($limit)->get();
    }

    public function recommendationReasonKeys(self $candidate): array
    {
        $reasons = [(int) $candidate->category_id === (int) $this->category_id ? 'same_category' : 'related_category'];
        $city = mb_strtolower(trim((string) $this->city));
        if ($this->recommendationCountryNames() !== []
            && in_array(mb_strtolower(trim((string) $candidate->country)), $this->recommendationCountryNames(), true)
            && $city !== '' && $city !== 'other'
            && $city === mb_strtolower(trim((string) $candidate->city))) {
            $reasons[] = 'same_city';
        }
        $price = (float) $this->price;
        if ($price > 0 && (float) $candidate->price > 0 && trim((string) $this->currency) !== ''
            && strtoupper(trim((string) $this->currency)) === strtoupper(trim((string) $candidate->currency))
            && abs((float) $candidate->price - $price) / $price <= 0.25) {
            $reasons[] = 'close_price';
        }

        return $reasons;
    }

    private function recommendationCountryNames(): array
    {
        $country = mb_strtolower(trim((string) $this->country));

        return match ($country) {
            '' => [],
            'iran', 'ir', 'ایران' => ['iran', 'ir', 'ایران'],
            default => [$country],
        };
    }

    public static function panelStatusOptions(): array
    {
        return [
            'pending' => __('panel::messages.pending'),
            'rejected' => __('panel::messages.rejected'),
            'active' => __('panel::messages.active'),
            'sold' => __('panel::messages.sold'),
            'expired' => __('panel::messages.expired'),
        ];
    }

    public static function panelStatusCountsForUser(int|string $userId): array
    {
        $counts = static::query()
            ->ownedByUser($userId)
            ->selectRaw('status, COUNT(*) as aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status');

        return [
            'all' => (int) $counts->sum(),
            'active' => (int) ($counts['active'] ?? 0),
            'pending' => (int) ($counts['pending'] ?? 0),
            'rejected' => (int) ($counts['rejected'] ?? 0),
            'sold' => (int) ($counts['sold'] ?? 0),
            'expired' => (int) ($counts['expired'] ?? 0),
        ];
    }

    public static function activeCount(): int
    {
        return (int) static::query()
            ->active()
            ->count();
    }

    public static function overviewStats(): array
    {
        $counts = static::query()
            ->selectRaw('COUNT(*) as total')
            ->selectRaw("SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active")
            ->selectRaw("SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending")
            ->selectRaw('SUM(CASE WHEN is_featured = true THEN 1 ELSE 0 END) as featured')
            ->first();

        return [
            'total' => (int) ($counts?->total ?? 0),
            'active' => (int) ($counts?->active ?? 0),
            'pending' => (int) ($counts?->pending ?? 0),
            'featured' => (int) ($counts?->featured ?? 0),
            'created_today' => (int) static::query()
                ->where('created_at', '>=', now()->startOfDay())
                ->count(),
        ];
    }

    public static function creationTrend(int $days): array
    {
        $safeDays = max(1, $days);
        $startDate = now()->startOfDay()->subDays($safeDays - 1);
        $countsByDate = static::query()
            ->selectRaw('DATE(created_at) as day, COUNT(*) as total')
            ->where('created_at', '>=', $startDate)
            ->groupBy('day')
            ->orderBy('day')
            ->pluck('total', 'day')
            ->all();
        $labels = [];
        $data = [];

        for ($index = 0; $index < $safeDays; $index++) {
            $date = $startDate->copy()->addDays($index);
            $dateKey = $date->toDateString();

            $labels[] = app()->getLocale() === 'fa' ? PersianLocale::date($date) : $date->format('M j');
            $data[] = (int) ($countsByDate[$dateKey] ?? 0);
        }

        return [
            'labels' => $labels,
            'data' => $data,
        ];
    }

    public static function homeFeatured(int $limit = 4): Collection
    {
        return static::query()
            ->active()
            ->where('is_featured', true)
            ->latest()
            ->take($limit)
            ->get();
    }

    public static function homeRecent(int $limit = 8): Collection
    {
        return static::query()
            ->active()
            ->latest()
            ->take($limit)
            ->get();
    }

    public static function panelIndexDataForUser(User $user, string $search, string $status): array
    {
        $listings = static::query()
            ->ownedByUser($user->getKey())
            ->withPanelIndexState()
            ->searchTerm($search)
            ->forPanelStatus($status)
            ->latest('id')
            ->paginate(10)
            ->withQueryString();

        $favoriteCounts = FavoriteListing::countsForListings(
            $listings->getCollection()->map(static fn (self $listing): int => (int) $listing->getKey())->all()
        );

        $listings->getCollection()->each(static function (self $listing) use ($favoriteCounts): void {
            $listing->setAttribute('favorited_by_users_count', $favoriteCounts[(int) $listing->getKey()] ?? 0);
        });

        return [
            'listings' => $listings,
            'counts' => static::panelStatusCountsForUser($user->getKey()),
        ];
    }

    public function panelPrimaryImageUrl(): ?string
    {
        return $this->primaryImageUrl('card', 'desktop');
    }

    public function panelPriceLabel(): string
    {
        if (is_null($this->price)) {
            return __('listing::messages.free');
        }

        return Money::format((string) $this->price, $this->currency);
    }

    public function panelStatusMeta(): array
    {
        return match ($this->statusValue()) {
            'sold' => [
                'label' => __('listing::messages.status_sold'),
                'badge_class' => 'is-success',
                'hint' => __('listing::messages.hint_sold'),
            ],
            'expired' => [
                'label' => __('listing::messages.status_expired'),
                'badge_class' => 'is-danger',
                'hint' => __('listing::messages.hint_expired'),
            ],
            'rejected' => [
                'label' => __('listing::messages.status_rejected'),
                'badge_class' => 'is-danger',
                'hint' => __('listing::messages.hint_rejected'),
            ],
            'pending' => [
                'label' => __('listing::messages.status_pending'),
                'badge_class' => 'is-warning',
                'hint' => __('listing::messages.hint_pending'),
            ],
            default => [
                'label' => __('listing::messages.status_live'),
                'badge_class' => 'is-primary',
                'hint' => __('listing::messages.hint_live'),
            ],
        };
    }

    public function panelLocationLabel(): string
    {
        $parts = collect([
            trim((string) $this->city),
            trim((string) $this->country),
        ])->filter()->values();

        return $parts->isNotEmpty() ? $parts->implode(', ') : 'Location not specified';
    }

    public function panelPublishedAt(): ?Carbon
    {
        $createdAt = $this->created_at?->copy();
        $expiresAt = $this->expires_at?->copy();

        if (! $createdAt) {
            return $expiresAt;
        }

        if (! $expiresAt || $expiresAt->greaterThanOrEqualTo($createdAt)) {
            return $createdAt;
        }

        return $expiresAt->subDays(self::DEFAULT_PANEL_EXPIRY_WINDOW_DAYS);
    }

    public function panelExpirySummary(): string
    {
        if (! $this->expires_at) {
            return 'No expiry limit';
        }

        $expiresAt = $this->expires_at->copy()->startOfDay();
        $days = Carbon::today()->diffInDays($expiresAt, false);

        return match (true) {
            $days > 0 => $days.' days left',
            $days === 0 => 'Ends today',
            default => 'Expired '.abs($days).' days ago',
        };
    }

    public function panelVideoSummary(int $total, int $ready, int $pending): ?array
    {
        if ($total < 1) {
            return null;
        }

        return [
            'label' => $total.' videos',
            'detail' => $ready.' ready'.($pending > 0 ? ', '.$pending.' processing' : ''),
        ];
    }

    public function replacePublicImage(string $absolutePath, ?string $fileName = null): void
    {
        if (! is_file($absolutePath)) {
            return;
        }

        $disk = $this->mediaDisk();
        $targetFileName = trim((string) ($fileName ?: basename($absolutePath)));
        $existingMediaItems = $this->getMedia('listing-images');

        if ($existingMediaItems->count() === 1) {
            $existingMedia = $existingMediaItems->first();

            if (
                $existingMedia
                && (string) $existingMedia->file_name === $targetFileName
                && (string) $existingMedia->disk === $disk
            ) {
                try {
                    if (is_file($existingMedia->getPath())) {
                        return;
                    }
                } catch (Throwable) {
                }
            }
        }

        $this->clearMediaCollection('listing-images');
        $this->attachListingImage($absolutePath, $targetFileName, $disk);
    }

    public function attachListingImage(string $absolutePath, string $fileName, ?string $disk = null): void
    {
        if (! is_file($absolutePath)) {
            return;
        }

        $targetDisk = is_string($disk) && trim($disk) !== ''
            ? trim($disk)
            : $this->mediaDisk();

        $this
            ->addMedia($absolutePath)
            ->usingFileName(trim($fileName))
            ->withCustomProperties(self::mediaCustomProperties())
            ->preservingOriginal()
            ->toMediaCollection('listing-images', $targetDisk);
    }

    public function statusValue(): string
    {
        return $this->status instanceof ListingStatus
            ? $this->status->getValue()
            : (string) $this->status;
    }

    public function statusLabel(): string
    {
        return match ($this->statusValue()) {
            'sold' => __('listing::messages.status_sold'),
            'expired' => __('listing::messages.status_expired'),
            'pending' => __('listing::messages.status_pending'),
            'rejected' => __('listing::messages.status_rejected'),
            default => __('listing::messages.status_active'),
        };
    }

    public function loadPanelEditor(): self
    {
        return $this->load([
            'category:id,name',
            'videos:id,listing_id,title,status,is_active,path,upload_path,duration_seconds,size',
        ]);
    }

    public function assertOwnedBy(User $user): void
    {
        abort_unless((int) $this->user_id === (int) $user->getKey(), 403);
    }

    public function trackViewBy(null|int|string $viewerId): void
    {
        if ((int) $this->user_id === (int) $viewerId) {
            return;
        }

        $this->increment('view_count');
        $this->refresh();
    }

    public function hasContactDetails(): bool
    {
        return filled($this->contact_phone) || filled($this->contact_email);
    }

    public function canRevealContactTo(?User $user): bool
    {
        if (! $user || ! $this->hasContactDetails() || ! $this->user_id) {
            return false;
        }

        return (int) $this->user_id !== (int) $user->getKey();
    }

    public function contactDetailsFor(?User $user): array
    {
        $canAccess = $this->canRevealContactTo($user)
            || ($user && $this->user_id && (int) $this->user_id === (int) $user->getKey());

        if (! $canAccess) {
            return [
                'phone' => '',
                'email' => '',
            ];
        }

        return [
            'phone' => filled($this->contact_phone) ? (string) $this->contact_phone : '',
            'email' => filled($this->contact_email) ? (string) $this->contact_email : '',
        ];
    }

    public function markAsSold(): void
    {
        DB::transaction(function (): void {
            $locked = static::query()->lockForUpdate()->findOrFail($this->getKey());
            $this->setRawAttributes($locked->getAttributes(), true);
            if (in_array($locked->statusValue(), ['pending', 'rejected'], true)) {
                throw ValidationException::withMessages(['status' => __('listing::messages.hint_pending')]);
            }
            $this->forceFill([
                'status' => 'sold',
            ])->save();
        });
    }

    public function republish(): void
    {
        DB::transaction(function (): void {
            $locked = static::query()->lockForUpdate()->findOrFail($this->getKey());
            $this->setRawAttributes($locked->getAttributes(), true);
            $this->forceFill([
                'status' => in_array($locked->statusValue(), ['pending', 'rejected'], true) ? 'pending' : 'active',
                'expires_at' => now()->addDays(self::DEFAULT_PANEL_EXPIRY_WINDOW_DAYS),
            ])->save();
        });
    }

    public function updateFromPanel(array $attributes): void
    {
        DB::transaction(function () use ($attributes): void {
            $locked = static::query()->lockForUpdate()->findOrFail($this->getKey());
            $this->setRawAttributes($locked->getAttributes(), true);
            $payload = Arr::only($attributes, [
                'title',
                'description',
                'price',
                'status',
                'contact_phone',
                'contact_email',
                'country',
                'city',
                'expires_at',
            ]);

            if (array_key_exists('currency', $attributes)) {
                $payload['currency'] = $this->currency ?: ListingPanelHelper::defaultCurrency();
            }

            if (array_key_exists('custom_fields', $attributes)) {
                $payload['custom_fields'] = $attributes['custom_fields'];
            }

            if (isset($payload['price'])) {
                $payload['price'] = Money::decimal($payload['price']);
            }

            if (in_array($locked->statusValue(), ['pending', 'rejected'], true)) {
                $payload['status'] = 'pending';
            }

            $this->forceFill($payload)->save();
        });
    }

    public static function createFromFrontend(array $data, null|int|string $userId): self
    {
        $baseSlug = Str::slug((string) ($data['title'] ?? 'listing'));
        $baseSlug = $baseSlug !== '' ? $baseSlug : 'listing';

        do {
            $slug = $baseSlug.'-'.Str::random(6);
        } while (static::query()->where('slug', $slug)->exists());

        $payload = Arr::only($data, [
            'title',
            'description',
            'price',
            'category_id',
            'contact_phone',
            'contact_email',
            'country',
            'city',
            'latitude',
            'longitude',
            'custom_fields',
            'expires_at',
        ]);

        $payload['user_id'] = $userId;
        $payload['currency'] = ListingPanelHelper::defaultCurrency();
        if (isset($payload['price'])) {
            $payload['price'] = Money::decimal($payload['price']);
        }
        $payload['slug'] = $slug;
        $payload['status'] = PendingListingStatus::class;

        $listing = static::query()->make()->forceFill($payload);
        $listing->save();

        return $listing;
    }

    public function applyAdminFormData(array $data): void
    {
        $data['currency'] = $this->exists ? $this->currency : ListingPanelHelper::defaultCurrency();
        if (isset($data['price'])) {
            $data['price'] = Money::decimal($data['price']);
        }

        $this->fill(Arr::only($data, [
            'title',
            'description',
            'price',
            'currency',
            'category_id',
            'contact_phone',
            'contact_email',
            'expires_at',
            'country',
            'city',
            'latitude',
            'longitude',
            'custom_fields',
            'images',
        ]));

        foreach (['slug', 'user_id', 'is_featured'] as $attribute) {
            if (array_key_exists($attribute, $data)) {
                $this->setAttribute($attribute, $data[$attribute]);
            }
        }

        if (array_key_exists('status', $data)) {
            $this->applyAdminStatus($data['status']);
        }
    }

    private function applyAdminStatus(mixed $status): void
    {
        $target = match (true) {
            $status instanceof ListingStatus => $status::class,
            is_string($status) && class_exists($status) => $status,
            default => match ((string) $status) {
                'active' => ActiveListingStatus::class,
                'pending' => PendingListingStatus::class,
                'rejected' => RejectedListingStatus::class,
                'sold' => SoldListingStatus::class,
                'expired' => ExpiredListingStatus::class,
                default => throw new \InvalidArgumentException('Invalid listing status.'),
            },
        };

        if ($target === $this->status::class) {
            return;
        }

        $this->status->transitionTo($target);
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('listing-images')->useDisk($this->mediaDisk());
    }

    public function registerMediaConversions(?Media $media = null): void
    {
        if ($this->shouldSkipConversionsForSeeder()) {
            return;
        }

        $this
            ->addMediaConversion('gallery-mobile')
            ->fit(Fit::Max, 960, 960)
            ->format('webp')
            ->quality(78)
            ->performOnCollections('listing-images')
            ->nonQueued();

        $this
            ->addMediaConversion('gallery-desktop')
            ->fit(Fit::Max, 1680, 1680)
            ->format('webp')
            ->quality(82)
            ->performOnCollections('listing-images')
            ->nonQueued();

        $this
            ->addMediaConversion('card-mobile')
            ->fit(Fit::Crop, 720, 540)
            ->format('webp')
            ->quality(76)
            ->performOnCollections('listing-images')
            ->nonQueued();

        $this
            ->addMediaConversion('card-desktop')
            ->fit(Fit::Crop, 1080, 810)
            ->format('webp')
            ->quality(80)
            ->performOnCollections('listing-images')
            ->nonQueued();

        $this
            ->addMediaConversion('thumb-mobile')
            ->fit(Fit::Crop, 220, 220)
            ->format('webp')
            ->quality(74)
            ->performOnCollections('listing-images')
            ->nonQueued();

        $this
            ->addMediaConversion('thumb-desktop')
            ->fit(Fit::Crop, 320, 320)
            ->format('webp')
            ->quality(78)
            ->performOnCollections('listing-images')
            ->nonQueued();
    }

    private function shouldSkipConversionsForSeeder(): bool
    {
        if ((bool) config('demo.provisioning', false)) {
            return true;
        }

        if (! app()->runningInConsole()) {
            return false;
        }

        $argv = implode(' ', (array) ($_SERVER['argv'] ?? []));

        return str_contains($argv, 'db:seed') || str_contains($argv, '--seed');
    }

    private function mediaDisk(): string
    {
        return LocalMedia::disk();
    }

    public static function mediaCustomProperties(): array
    {
        $scope = static::mediaPathScope();

        return $scope !== null
            ? ['path_scope' => $scope]
            : [];
    }

    public static function mediaPathScope(): ?string
    {
        $connection = (string) config('database.default', 'pgsql');
        $searchPath = config("database.connections.{$connection}.search_path");
        $value = is_array($searchPath)
            ? implode('_', $searchPath)
            : (string) $searchPath;
        $scope = (string) Str::of($value)
            ->before(',')
            ->trim()
            ->lower()
            ->replaceMatches('/[^a-z0-9_]+/', '_')
            ->trim('_');

        return $scope !== '' ? $scope : null;
    }

    protected function location(): Attribute
    {
        return Attribute::make(
            get: function (mixed $value, array $attributes): ?array {
                $latitude = $attributes['latitude'] ?? null;
                $longitude = $attributes['longitude'] ?? null;

                if ($latitude === null || $longitude === null) {
                    return null;
                }

                return [
                    'lat' => (float) $latitude,
                    'lng' => (float) $longitude,
                ];
            },
            set: fn (?array $value): array => [
                'latitude' => is_array($value) ? ($value['lat'] ?? null) : null,
                'longitude' => is_array($value) ? ($value['lng'] ?? null) : null,
            ],
        );
    }
}
