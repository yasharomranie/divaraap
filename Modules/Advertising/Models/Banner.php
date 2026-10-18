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
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

final class Banner extends Model
{
    use LogsActivity;
    use SoftDeletes;

    public static function moderationQueue(): Collection
    {
        return self::query()->where('review_status', 'pending')->oldest()->limit(20)->get(['id', 'title', 'alt', 'image', 'mobile_image', 'created_at']);
    }

    public function moderate(bool $approve, ?string $note = null): void
    {
        $this->forceFill(['review_status' => $approve ? 'approved' : 'rejected', 'is_active' => $approve])->save();
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logOnly(['title', 'device', 'is_active', 'review_status', 'starts_at', 'ends_at', 'campaign_id', 'daily_frequency_cap'])->logOnlyDirty()->dontLogEmptyChanges();
    }

    protected $table = 'advertising_banners';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['daily_frequency_cap' => 'integer', 'is_active' => 'boolean', 'new_tab' => 'boolean', 'starts_at' => 'datetime', 'ends_at' => 'datetime'];
    }

    protected static function booted(): void
    {
        self::saving(function (self $banner): void {
            if (auth()->check() && ! auth()->user()->hasRole('admin') && $banner->isDirty(['title', 'image', 'mobile_image', 'alt', 'url'])) {
                $banner->review_status = 'pending';
                $banner->is_active = false;
            }
        });
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
        return $this->campaign_id === null || ($this->campaign && $this->campaign->eligible($context, $forDelivery));
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
        $url = trim((string) $this->url);

        return preg_match('~^https?://~i', $url) && filter_var($url, FILTER_VALIDATE_URL) ? $url : null;
    }
}
