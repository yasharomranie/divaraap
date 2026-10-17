<?php

declare(strict_types=1);

namespace Modules\Advertising\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

final class BannerAssignment extends Model
{
    use SoftDeletes;

    protected $table = 'advertising_assignments';

    protected $guarded = ['id'];

    public function banner(): BelongsTo
    {
        return $this->belongsTo(Banner::class, 'banner_id');
    }
}
