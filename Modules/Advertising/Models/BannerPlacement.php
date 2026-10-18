<?php

declare(strict_types=1);

namespace Modules\Advertising\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

final class BannerPlacement extends Model
{
    use LogsActivity;
    use SoftDeletes;

    public const KEYS = ['home-top', 'home-feed', 'listings-feed', 'search-feed', 'category-feed', 'listing-details', 'listing-related', 'page-top', 'page-bottom'];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logOnly(['key', 'is_active', 'first_after', 'interval', 'ordering', 'repeat', 'device'])->logOnlyDirty()->dontLogEmptyChanges();
    }

    protected $table = 'advertising_placements';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'repeat' => 'boolean', 'first_after' => 'integer', 'interval' => 'integer'];
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(BannerAssignment::class, 'placement_id')->orderBy('priority')->orderBy('id');
    }

    public function slotIndex(int $position): ?int
    {
        if (! str_ends_with($this->key, '-feed')) {
            return $position === 0 ? 0 : null;
        }
        if ($position < $this->first_after || $this->interval < 1 || ($position - $this->first_after) % $this->interval !== 0) {
            return null;
        }

        return intdiv($position - $this->first_after, $this->interval);
    }

    public static function available(string $key): ?self
    {
        return self::query()->where('key', $key)->where('is_active', true)
            ->with(['assignments.banner' => fn ($q) => $q->available()->with('campaign')])->first();
    }
}
