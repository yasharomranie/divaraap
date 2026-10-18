<?php

declare(strict_types=1);

namespace Modules\Advertising\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Admin\App\Settings\OperationsSettings;
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
        return ['impression_limit' => 'integer', 'click_limit' => 'integer', 'cost_limit' => 'integer', 'impression_cost' => 'integer', 'click_cost' => 'integer', 'recorded_impressions' => 'integer', 'recorded_clicks' => 'integer', 'recorded_cost' => 'integer', 'delivery_weight' => 'integer', 'daily_frequency_cap' => 'integer', 'is_active' => 'boolean', 'starts_at' => 'datetime', 'ends_at' => 'datetime', 'planned_budget' => 'integer', 'category_ids' => 'array', 'city_ids' => 'array', 'page_paths' => 'array'];
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

    public function eligible(array $context, bool $forDelivery = true): bool
    {
        if (! $this->is_active || ($this->starts_at && $this->starts_at->isFuture()) || ($this->ends_at && $this->ends_at->lessThanOrEqualTo(now()))) {
            return false;
        }
        if ($forDelivery && $this->deliveryState() !== 'running') {
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

    public function deliveryState(): string
    {
        if (! $this->is_active) {
            return 'paused';
        }
        if ($this->starts_at?->isFuture()) {
            return 'scheduled';
        }
        if ($this->ends_at?->lessThanOrEqualTo(now())) {
            return 'ended';
        }
        if ($this->impression_limit !== null && $this->recorded_impressions >= $this->impression_limit) {
            return 'impression_limit';
        }
        if ($this->click_limit !== null && $this->recorded_clicks >= $this->click_limit) {
            return 'click_limit';
        }
        if ($this->cost_limit !== null && ($this->recorded_cost >= $this->cost_limit || $this->cost_limit - $this->recorded_cost < max($this->impression_cost, $this->click_cost))) {
            return 'cost_limit';
        }
        if (($this->impression_limit !== null || $this->click_limit !== null || $this->cost_limit !== null) && ! app(OperationsSettings::class)->telemetry_enabled) {
            return 'telemetry_off';
        }

        return 'running';
    }

    public static function recordDelivery(int $id, string $kind, array $pricing): void
    {
        $campaign = self::query()->lockForUpdate()->find($id);
        if (! $campaign || ! in_array($kind, ['impression', 'click'], true)) {
            return;
        }
        $counter = $kind === 'impression' ? 'recorded_impressions' : 'recorded_clicks';
        $campaign->$counter++;
        $campaign->recorded_cost += max(0, min(1000000000, (int) ($pricing[$kind] ?? 0)));
        $campaign->saveQuietly();
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logOnly(['title', 'goal', 'is_active', 'starts_at', 'ends_at', 'planned_budget', 'category_ids', 'city_ids', 'page_paths', 'daily_frequency_cap', 'delivery_weight', 'impression_limit', 'click_limit', 'cost_limit', 'impression_cost', 'click_cost'])->logOnlyDirty()->dontLogEmptyChanges();
    }
}
