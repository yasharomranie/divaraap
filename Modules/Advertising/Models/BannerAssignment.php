<?php

declare(strict_types=1);

namespace Modules\Advertising\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Validation\ValidationException;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

final class BannerAssignment extends Model
{
    use LogsActivity;
    use SoftDeletes;

    protected static function booted(): void
    {
        self::saving(function (self $assignment): void {
            $banner = Banner::query()->find($assignment->banner_id);
            $key = BannerPlacement::query()->whereKey($assignment->placement_id)->value('key');
            if ($banner && $key && ! $banner->eligibleForPlacement($key)) {
                throw ValidationException::withMessages(['placement_id' => __('advertising::sponsor.placement_invalid')]);
            }
        });
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logOnly(['placement_id', 'banner_id', 'priority'])->logOnlyDirty()->dontLogEmptyChanges();
    }

    protected $table = 'advertising_assignments';

    protected $guarded = ['id'];

    public function banner(): BelongsTo
    {
        return $this->belongsTo(Banner::class, 'banner_id');
    }
}
