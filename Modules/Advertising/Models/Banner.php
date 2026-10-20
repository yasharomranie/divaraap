<?php

declare(strict_types=1);

namespace Modules\Advertising\Models;

use App\Support\Money;
use App\Support\PersianSearch;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Modules\Category\Models\Category;
use Modules\Listing\Models\Listing;
use Modules\Location\Models\City;
use Modules\User\App\Models\Profile;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

final class Banner extends Model
{
    use LogsActivity;
    use SoftDeletes;

    private bool $adShopLoaded = false;

    private ?array $adShop = null;

    private ?array $adListings = null;

    private ?array $adService = null;

    private bool $adServiceLoaded = false;

    private ?array $adFilters = null;

    private ?string $adSponsoredCategory = null;

    private bool $adSponsoredCategoryLoaded = false;

    public static function moderationQueue(): Collection
    {
        return self::query()->where('review_status', 'pending')->oldest()->limit(20)->get(['id', 'title', 'alt', 'image', 'mobile_image', 'creative_format', 'shop_user_id', 'shop_snapshot', 'collection_listing_ids', 'service_listing_id', 'filter_choices', 'sponsored_category_id', 'created_at']);
    }

    public function moderate(bool $approve, ?string $note = null): void
    {
        $this->forceFill(['review_status' => $approve ? 'approved' : 'rejected', 'is_active' => $approve])->save();
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logOnly(['title', 'device', 'is_active', 'review_status', 'starts_at', 'ends_at', 'campaign_id', 'daily_frequency_cap', 'creative_format', 'shop_user_id', 'collection_listing_ids', 'service_listing_id', 'filter_choices', 'sponsored_category_id'])->logOnlyDirty()->dontLogEmptyChanges();
    }

    protected $table = 'advertising_banners';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['filter_choices' => 'array', 'collection_listing_ids' => 'array', 'shop_snapshot' => 'array', 'daily_frequency_cap' => 'integer', 'is_active' => 'boolean', 'new_tab' => 'boolean', 'starts_at' => 'datetime', 'ends_at' => 'datetime'];
    }

    protected static function booted(): void
    {
        self::saving(function (self $banner): void {
            $banner->creative_format ??= 'image';
            if (! in_array($banner->creative_format, ['image', 'shop', 'collection', 'service', 'filter', 'sponsor'], true)) {
                throw ValidationException::withMessages(['creative_format' => __('advertising::shop.invalid')]);
            }
            if (in_array($banner->creative_format, ['shop', 'sponsor'], true)) {
                $shop = Profile::advertisingShops([(int) $banner->shop_user_id])[(int) $banner->shop_user_id] ?? null;
                if (! $shop && (! $banner->exists || $banner->is_active || $banner->isDirty(['creative_format', 'shop_user_id']))) {
                    throw ValidationException::withMessages(['shop_user_id' => __('advertising::shop.invalid')]);
                }
                if ($shop && $banner->shop_snapshot != $shop) {
                    $banner->shop_snapshot = $shop;
                }
            } else {
                $banner->shop_user_id = null;
                $banner->shop_snapshot = null;
            }
            $banner->adListings = null;
            if ($banner->creative_format === 'collection') {
                $ids = $banner->collection_listing_ids;
                if (! is_array($ids) || count($ids) < 2 || count($ids) > 6
                    || collect($ids)->contains(fn ($id): bool => (! is_int($id) && ! is_string($id)) || ! preg_match('/^[1-9][0-9]{0,17}$/', (string) $id))
                    || count(array_unique($ids)) !== count($ids)) {
                    throw ValidationException::withMessages(['collection_listing_ids' => __('advertising::collection.invalid')]);
                }
                $ids = array_map('intval', $ids);
                $items = Listing::advertisingCollectionItems($ids);
                if (count($items) !== count($ids) && (! $banner->exists || $banner->is_active || $banner->isDirty(['creative_format', 'collection_listing_ids']))) {
                    throw ValidationException::withMessages(['collection_listing_ids' => __('advertising::collection.invalid')]);
                }
                $banner->collection_listing_ids = $ids;
            } else {
                $banner->collection_listing_ids = null;
            }
            $banner->adServiceLoaded = false;
            $banner->adService = null;
            if ($banner->creative_format === 'service') {
                if (! $banner->advertisedService() && (! $banner->exists || $banner->is_active || $banner->isDirty(['creative_format', 'service_listing_id']))) {
                    throw ValidationException::withMessages(['service_listing_id' => __('advertising::service.invalid')]);
                }
            } else {
                $banner->service_listing_id = null;
            }
            $banner->adFilters = null;
            if ($banner->creative_format === 'filter') {
                $choices = $banner->normalizedFilterChoices(Category::advertisingFilterOptions(), City::advertisingFilterOptions());
                if ($choices === null && (! $banner->exists || $banner->is_active || $banner->isDirty(['creative_format', 'filter_choices']))) {
                    throw ValidationException::withMessages(['filter_choices' => __('advertising::filters.invalid')]);
                }
                if ($choices !== null) {
                    $banner->filter_choices = $choices;
                }
            } else {
                $banner->filter_choices = null;
            }
            $banner->adSponsoredCategoryLoaded = false;
            $banner->adSponsoredCategory = null;
            if ($banner->creative_format === 'sponsor') {
                if ($banner->sponsoredCategoryName() === null && (! $banner->exists || $banner->is_active || $banner->isDirty(['creative_format', 'sponsored_category_id']))) {
                    throw ValidationException::withMessages(['sponsored_category_id' => __('advertising::sponsor.invalid')]);
                }
            } else {
                $banner->sponsored_category_id = null;
            }
            $banner->adShopLoaded = false;
            $banner->adShop = null;
            if (auth()->check() && ! auth()->user()->hasRole('admin') && $banner->isDirty(['title', 'image', 'mobile_image', 'alt', 'url', 'creative_format', 'shop_user_id', 'shop_snapshot', 'collection_listing_ids', 'service_listing_id', 'filter_choices', 'sponsored_category_id'])) {
                $banner->review_status = 'pending';
                $banner->is_active = false;
            }
        });
    }

    public static function healthIssues(): array
    {
        $issues = [];
        $banners = self::query()->where('is_active', true)->where('review_status', 'approved')
            ->where(fn (Builder $query) => $query->whereNull('ends_at')->orWhere('ends_at', '>', now()))->with('campaign:id,is_active')->get();
        foreach ($banners as $banner) {
            if ($banner->campaign_id !== null && ! $banner->campaign?->is_active) {
                continue;
            }
            $codes = [];
            if ($banner->creative_format === 'filter' && $banner->preparedFilters() === []) {
                $codes[] = 'filters_unavailable';
            }
            if ($banner->creative_format === 'service' && ! $banner->advertisedService()) {
                $codes[] = 'service_unavailable';
            }
            if ($banner->creative_format === 'collection' && count($banner->advertisedListings()) < count($banner->collection_listing_ids ?? [])) {
                $codes[] = 'collection_unavailable';
            }
            if ($banner->creative_format === 'sponsor' && $banner->sponsoredCategoryName() === null) {
                $codes[] = 'sponsor_category_unavailable';
            }
            if (in_array($banner->creative_format, ['shop', 'sponsor'], true) && ! $banner->shopCreativeEligible()) {
                $codes[] = 'shop_unavailable';
            }
            foreach (['image' => 'image_missing', 'mobile_image' => 'mobile_image_missing'] as $field => $code) {
                if ($field === 'mobile_image' && ! filled($banner->$field)) {
                    continue;
                }
                try {
                    $exists = filled($banner->$field) && Storage::disk('public')->exists($banner->$field);
                } catch (\Throwable) {
                    $exists = false;
                }
                if (! $exists) {
                    $codes[] = $code;
                }
            }
            if (in_array($banner->creative_format, [null, 'image'], true) && filled($banner->url) && $banner->destination() === null) {
                $codes[] = 'link_invalid';
            }
            foreach ($codes as $code) {
                $issues[] = ['subject_type' => 'banner', 'subject_id' => $banner->id, 'subject_title' => $banner->title, 'code' => $code];
            }
        }

        return $issues;
    }

    public static function previewOptions(): array
    {
        return self::query()->orderByDesc('id')->pluck('title', 'id')->all();
    }

    public static function forPreview(int $id): self
    {
        return self::query()->findOrFail($id);
    }

    public function frequencyLimits(): array
    {
        $limits = [];
        if ($this->daily_frequency_cap !== null) {
            $limits['banner:'.$this->id] = max(1, $this->daily_frequency_cap);
        }
        if ($this->campaign?->daily_frequency_cap !== null) {
            $limits['campaign:'.$this->campaign_id] = max(1, $this->campaign->daily_frequency_cap);
        }

        return $limits;
    }

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class);
    }

    public function eligibleForCampaign(array $context, bool $forDelivery = true): bool
    {
        return ($this->creative_format !== 'sponsor' || (int) ($context['category_id'] ?? 0) === (int) $this->sponsored_category_id) && $this->creativeEligible() && ($this->campaign_id === null || ($this->campaign && $this->campaign->eligible($context, $forDelivery)));
    }

    public function cacheAdvertisedListings(array $items): void
    {
        $this->adListings = $items;
    }

    public function advertisedListings(): array
    {
        if ($this->creative_format !== 'collection') {
            return [];
        }
        $ids = $this->collection_listing_ids ?? [];
        if (! is_array($ids) || count($ids) < 2 || count($ids) > 6) {
            return [];
        }
        $this->adListings ??= Listing::advertisingCollectionItems($ids);
        $ordered = [];
        foreach ($ids as $id) {
            if (isset($this->adListings[$id])) {
                $ordered[(int) $id] = $this->adListings[$id];
            }
        }

        return $ordered;
    }

    public function creativeEligible(): bool
    {
        return match ($this->creative_format) {
            'sponsor' => $this->sponsoredCategoryName() !== null && $this->shopCreativeEligible(),
            'filter' => count($this->preparedFilters()) >= 2,
            'service' => $this->advertisedService() !== null,
            'collection' => count($this->advertisedListings()) >= 2,
            default => $this->shopCreativeEligible(),
        };
    }

    public function eligibleForPlacement(string $key): bool
    {
        return $this->creative_format === 'sponsor' ? $key === 'category-top' : $key !== 'category-top';
    }

    public function cacheSponsoredCategory(?string $name): void
    {
        $this->adSponsoredCategory = $name;
        $this->adSponsoredCategoryLoaded = true;
    }

    public function sponsoredCategoryName(): ?string
    {
        if ($this->creative_format !== 'sponsor' || ! $this->sponsored_category_id) {
            return null;
        }
        if (! $this->adSponsoredCategoryLoaded) {
            $this->cacheSponsoredCategory(Category::advertisingFilterOptions()[(int) $this->sponsored_category_id] ?? null);
        }

        return $this->adSponsoredCategory;
    }

    public function sponsorshipSignature(): ?string
    {
        if ($this->creative_format !== 'sponsor') {
            return null;
        }

        return hash('sha256', json_encode([(int) $this->sponsored_category_id, (int) $this->shop_user_id,
            $this->shop_snapshot['name'] ?? null, $this->shop_snapshot['description'] ?? null, $this->shop_snapshot['hours'] ?? null,
        ], JSON_THROW_ON_ERROR));
    }

    private function normalizedFilterChoices(array $categories, array $cities): ?array
    {
        $choices = $this->filter_choices;
        if (! is_array($choices) || count($choices) < 2 || count($choices) > 4) {
            return null;
        }
        $normalized = [];
        $seen = [];
        foreach ($choices as $choice) {
            if (! is_array($choice) || array_diff(array_keys($choice), ['label', 'search', 'category_id', 'city_id', 'min_price', 'max_price', 'sort']) !== []
                || ! is_string($choice['label'] ?? null) || trim($choice['label']) === '' || mb_strlen($choice['label']) > 60) {
                return null;
            }
            $row = ['label' => trim($choice['label'])];
            $search = $choice['search'] ?? '';
            if (! is_string($search) || PersianSearch::terms($search) === null) {
                return null;
            }
            if (trim($search) !== '') {
                $row['search'] = trim($search);
            }
            foreach (['category_id' => $categories, 'city_id' => $cities] as $field => $options) {
                $value = $choice[$field] ?? null;
                if ($value === null || $value === '') {
                    continue;
                }
                if ((! is_int($value) && ! is_string($value)) || ! preg_match('/^[1-9][0-9]{0,17}$/', (string) $value) || ! isset($options[(int) $value])) {
                    return null;
                }
                $row[$field] = (int) $value;
            }
            foreach (['min_price', 'max_price'] as $field) {
                $value = $choice[$field] ?? null;
                if ($value === null || $value === '') {
                    continue;
                }
                if (! Money::valid($value, 'IRR')) {
                    return null;
                }
                $row[$field] = Money::decimal($value);
            }
            if (count($row) === 1 || (isset($row['min_price'], $row['max_price']) && bccomp($row['min_price'], $row['max_price'], 2) > 0)) {
                return null;
            }
            $row['sort'] = $choice['sort'] ?? 'newest';
            if (! in_array($row['sort'], ['smart', 'newest', 'oldest', 'price_asc', 'price_desc'], true)) {
                return null;
            }
            $filters = $row;
            unset($filters['label']);
            $signature = json_encode($filters, JSON_THROW_ON_ERROR);
            if (isset($seen[$signature])) {
                return null;
            }
            $seen[$signature] = true;
            $normalized[] = $row;
        }

        return $normalized;
    }

    public function preparedFilters(?array $categories = null, ?array $cities = null): array
    {
        if ($this->creative_format !== 'filter') {
            return [];
        }
        if ($this->adFilters !== null && $categories === null && $cities === null) {
            return $this->adFilters;
        }
        $categories ??= Category::advertisingFilterOptions();
        $cities ??= City::advertisingFilterOptions();
        $rows = $this->normalizedFilterChoices($categories, $cities);
        $output = [];
        foreach ($rows ?? [] as $row) {
            $query = [];
            $summary = [];
            if (isset($row['search'])) {
                $query['search'] = $row['search'];
                $summary[] = $row['search'];
            }
            if (isset($row['category_id'])) {
                $query['category'] = $row['category_id'];
                $summary[] = $categories[$row['category_id']];
            }
            if (isset($row['city_id'])) {
                $city = $cities[$row['city_id']];
                $query['country'] = $city['country_id'];
                $query['city'] = $row['city_id'];
                $summary[] = $city['name'];
            }
            foreach (['min_price' => 'minimum', 'max_price' => 'maximum'] as $field => $label) {
                if (isset($row[$field])) {
                    $query[$field] = $row[$field];
                    $summary[] = __('advertising::filters.'.$label, ['price' => Money::format($row[$field], 'IRR')]);
                }
            }
            $query['sort'] = $row['sort'];
            $key = hash('sha256', json_encode([$row, $query], JSON_THROW_ON_ERROR));
            $output[$key] = ['label' => $row['label'], 'summary' => implode(' · ', $summary), 'url' => route('listings.index', $query)];
        }
        $this->adFilters = $output;

        return $output;
    }

    public function cacheAdvertisedService(?array $service): void
    {
        $this->adService = $service;
        $this->adServiceLoaded = true;
    }

    public function advertisedService(): ?array
    {
        if ($this->creative_format !== 'service' || ! $this->service_listing_id) {
            return null;
        }
        if (! $this->adServiceLoaded) {
            $this->cacheAdvertisedService(Listing::advertisingServiceItems([(int) $this->service_listing_id])[(int) $this->service_listing_id] ?? null);
        }

        return $this->adService;
    }

    public function cacheAdvertisedShop(?array $shop): void
    {
        $this->adShopLoaded = true;
        $this->adShop = $shop;
    }

    public function shopCreativeEligible(): bool
    {
        if (($this->creative_format ?? 'image') === 'image') {
            return true;
        }
        if (! in_array($this->creative_format, ['shop', 'sponsor'], true) || ! $this->shop_user_id || ! is_array($this->shop_snapshot)) {
            return false;
        }
        if (! $this->adShopLoaded) {
            $this->cacheAdvertisedShop(Profile::advertisingShops([(int) $this->shop_user_id])[(int) $this->shop_user_id] ?? null);
        }

        return $this->adShop !== null && $this->adShop == $this->shop_snapshot;
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(BannerAssignment::class, 'banner_id');
    }

    public function scopeAvailable(Builder $query): Builder
    {
        $now = now();

        return $query->where('is_active', true)->where('review_status', 'approved')
            ->where(fn (Builder $q) => $q->whereNull('starts_at')->orWhere('starts_at', '<=', $now))
            ->where(fn (Builder $q) => $q->whereNull('ends_at')->orWhere('ends_at', '>', $now));
    }

    public function imageUrl(bool $mobile = false): string
    {
        return Storage::disk('public')->url($mobile && $this->mobile_image ? $this->mobile_image : $this->image);
    }

    public function destination(): ?string
    {
        if ($this->creative_format === 'service') {
            return $this->advertisedService() ? route('listings.show', ['listing' => (int) $this->service_listing_id, 'request-service' => 1]).'#service-request' : null;
        }
        if (in_array($this->creative_format, ['collection', 'filter'], true)) {
            return null;
        }
        if (in_array($this->creative_format, ['shop', 'sponsor'], true)) {
            return $this->creativeEligible() ? route('sellers.show', (int) $this->shop_user_id) : null;
        }
        $url = trim((string) $this->url);

        return preg_match('~^https?://~i', $url) && filter_var($url, FILTER_VALIDATE_URL) ? $url : null;
    }
}
