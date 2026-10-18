<?php

declare(strict_types=1);

namespace Modules\Advertising\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

final class Campaign extends Model
{
    use LogsActivity;
    use SoftDeletes;

    protected $table = 'advertising_campaigns';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'starts_at' => 'datetime', 'ends_at' => 'datetime', 'planned_budget' => 'integer', 'category_ids' => 'array', 'city_ids' => 'array', 'page_paths' => 'array'];
    }

    protected static function booted(): void
    {
        self::saving(function (self $campaign): void {
            if ($campaign->isDirty('page_paths')) {
                $campaign->page_paths = array_values(array_unique(array_map(fn (string $path): string => '/'.trim($path, '/'), $campaign->page_paths ?? [])));
            }
        });
    }

    public function banners(): HasMany
    {
        return $this->hasMany(Banner::class, 'campaign_id');
    }

    public static function options(): array
    {
        return self::query()->orderBy('title')->pluck('title', 'id')->all();
    }

    public function eligible(array $context): bool
    {
        if (! $this->is_active || ($this->starts_at && $this->starts_at->isFuture()) || ($this->ends_at && $this->ends_at->lessThanOrEqualTo(now()))) {
            return false;
        }
        foreach (['category_ids' => 'category_id', 'city_ids' => 'city_id'] as $field => $key) {
            if ($this->$field && ! in_array((int) ($context[$key] ?? 0), array_map('intval', $this->$field), true)) {
                return false;
            }
        }
        if ($this->page_paths && ! in_array('/'.trim((string) ($context['path'] ?? request()->path()), '/'), $this->page_paths, true)) {
            return false;
        }

        return true;
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logOnly(['title', 'goal', 'is_active', 'starts_at', 'ends_at', 'planned_budget', 'category_ids', 'city_ids', 'page_paths'])->logOnlyDirty()->dontLogEmptyChanges();
    }
}
