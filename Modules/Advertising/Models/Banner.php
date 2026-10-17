<?php

declare(strict_types=1);

namespace Modules\Advertising\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;

final class Banner extends Model
{
    use SoftDeletes;

    protected $table = 'advertising_banners';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'new_tab' => 'boolean', 'starts_at' => 'datetime', 'ends_at' => 'datetime'];
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(BannerAssignment::class, 'banner_id');
    }

    public function scopeAvailable(Builder $query): Builder
    {
        $now = now();

        return $query->where('is_active', true)
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
