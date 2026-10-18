<?php

declare(strict_types=1);

namespace Modules\Advertising\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Cache;

final class FrequencyCount extends Model
{
    use SoftDeletes;

    protected $table = 'advertising_frequency_counts';

    protected $guarded = ['id'];

    public static function forVisitor(string $visitor): array
    {
        return self::query()->where('visitor', $visitor)->where('count_date', now()->toDateString())->get()
            ->mapWithKeys(fn (self $row) => [$row->scope.':'.$row->subject_id => (int) $row->impressions])->all();
    }

    public static function record(string $visitor, array $limits): void
    {
        foreach ($limits as $key => $limit) {
            [$scope, $id] = explode(':', $key);
            $attributes = ['visitor' => $visitor, 'scope' => $scope, 'subject_id' => (int) $id, 'count_date' => now()->toDateString()];
            self::query()->insertOrIgnore($attributes + ['impressions' => 0, 'created_at' => now(), 'updated_at' => now()]);
            self::withTrashed()->where($attributes)->increment('impressions', 1, ['updated_at' => now()]);
        }
        request()->attributes->remove('advertising.frequency.counts.'.now()->toDateString());
        if (Cache::add('advertising-frequency-retention', true, now()->addDay())) {
            self::withTrashed()->where('count_date', '<', now()->subDays(7)->toDateString())->forceDelete();
        }
    }
}
