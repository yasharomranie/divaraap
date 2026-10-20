<?php

declare(strict_types=1);

namespace Modules\Advertising\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Modules\Listing\Models\Listing;
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

    public static function moderationQueue(): Collection
    {
        return self::query()->where('review_status', 'pending')->oldest()->limit(20)->get(['id', 'title', 'alt', 'image', 'mobile_image', 'creative_format', 'shop_user_id', 'shop_snapshot', 'collection_listing_ids', 'created_at']);
    }

    public function moderate(bool $approve, ?string $note = null): void
    {
        $this->forceFill(['review_status' => $approve ? 'approved' : 'rejected', 'is_active' => $approve])->save();
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logOnly(['title', 'device', 'is_active', 'review_status', 'starts_at', 'ends_at', 'campaign_id', 'daily_frequency_cap', 'creative_format', 'shop_user_id', 'collection_listing_ids'])->logOnlyDirty()->dontLogEmptyChanges();
    }

    protected $table = 'advertising_banners';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['collection_listing_ids' => 'array', 'shop_snapshot' => 'array', 'daily_frequency_cap' => 'integer', 'is_active' => 'boolean', 'new_tab' => 'boolean', 'starts_at' => 'datetime', 'ends_at' => 'datetime'];
    }

    protected static function booted(): void
    {
        self::saving(function (self $banner): void {
            $banner->creative_format ??= 'image';
            if (! in_array($banner->creative_format, ['image', 'shop', 'collection'], true)) {
                throw ValidationException::withMessages(['creative_format' => __('advertising::shop.invalid')]);
            }
            if ($banner->creative_format === 'shop') {
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
            $banner->adShopLoaded = false;
            $banner->adShop = null;
            if (auth()->check() && ! auth()->user()->hasRole('admin') && $banner->isDirty(['title', 'image', 'mobile_image', 'alt', 'url', 'creative_format', 'shop_user_id', 'shop_snapshot', 'collection_listing_ids'])) {
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
            if ($banner->creative_format === 'collection' && count($banner->advertisedListings()) < count($banner->collection_listing_ids ?? [])) {
                $codes[] = 'collection_unavailable';
            }
            if ($banner->creative_format === 'shop' && ! $banner->shopCreativeEligible()) {
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
        return $this->creativeEligible() && ($this->campaign_id === null || ($this->campaign && $this->campaign->eligible($context, $forDelivery)));
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
        return $this->creative_format === 'collection' ? count($this->advertisedListings()) >= 2 : $this->shopCreativeEligible();
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
        if ($this->creative_format !== 'shop' || ! $this->shop_user_id || ! is_array($this->shop_snapshot)) {
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
        if ($this->creative_format === 'collection') {
            return null;
        }
        if ($this->creative_format === 'shop') {
            return $this->shopCreativeEligible() ? route('sellers.show', (int) $this->shop_user_id) : null;
        }
        $url = trim((string) $this->url);

        return preg_match('~^https?://~i', $url) && filter_var($url, FILTER_VALIDATE_URL) ? $url : null;
    }
}
