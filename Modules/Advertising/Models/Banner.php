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
use Modules\User\App\Models\Profile;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

final class Banner extends Model
{
    use LogsActivity;
    use SoftDeletes;

    private bool $adShopLoaded = false;

    private ?array $adShop = null;

    public static function moderationQueue(): Collection
    {
        return self::query()->where('review_status', 'pending')->oldest()->limit(20)->get(['id', 'title', 'alt', 'image', 'mobile_image', 'creative_format', 'shop_user_id', 'shop_snapshot', 'created_at']);
    }

    public function moderate(bool $approve, ?string $note = null): void
    {
        $this->forceFill(['review_status' => $approve ? 'approved' : 'rejected', 'is_active' => $approve])->save();
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logOnly(['title', 'device', 'is_active', 'review_status', 'starts_at', 'ends_at', 'campaign_id', 'daily_frequency_cap', 'creative_format', 'shop_user_id'])->logOnlyDirty()->dontLogEmptyChanges();
    }

    protected $table = 'advertising_banners';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['shop_snapshot' => 'array', 'daily_frequency_cap' => 'integer', 'is_active' => 'boolean', 'new_tab' => 'boolean', 'starts_at' => 'datetime', 'ends_at' => 'datetime'];
    }

    protected static function booted(): void
    {
        self::saving(function (self $banner): void {
            $banner->creative_format ??= 'image';
            if (! in_array($banner->creative_format, ['image', 'shop'], true)) {
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
            $banner->adShopLoaded = false;
            $banner->adShop = null;
            if (auth()->check() && ! auth()->user()->hasRole('admin') && $banner->isDirty(['title', 'image', 'mobile_image', 'alt', 'url', 'creative_format', 'shop_user_id', 'shop_snapshot'])) {
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
            if ($banner->creative_format !== 'shop' && filled($banner->url) && $banner->destination() === null) {
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
        return $this->shopCreativeEligible() && ($this->campaign_id === null || ($this->campaign && $this->campaign->eligible($context, $forDelivery)));
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
        if ($this->creative_format === 'shop') {
            return $this->shopCreativeEligible() ? route('sellers.show', (int) $this->shop_user_id) : null;
        }
        $url = trim((string) $this->url);

        return preg_match('~^https?://~i', $url) && filter_var($url, FILTER_VALIDATE_URL) ? $url : null;
    }
}
