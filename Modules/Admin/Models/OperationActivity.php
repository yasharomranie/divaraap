<?php

declare(strict_types=1);

namespace Modules\Admin\Models;

use Spatie\Activitylog\Models\Activity;

final class OperationActivity extends Activity
{
    public static function recentSummary(): array
    {
        return self::query()->latest()->limit(25)->get(['id', 'description', 'subject_type', 'subject_id', 'causer_id', 'created_at'])->toArray();
    }
}
