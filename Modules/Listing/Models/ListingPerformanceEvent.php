<?php

declare(strict_types=1);

namespace Modules\Listing\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Http\Request;
use Modules\Admin\App\Settings\OperationsSettings;

final class ListingPerformanceEvent extends Model
{
    use SoftDeletes;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['event_day' => 'immutable_date'];
    }

    public static function record(Listing $listing, string $kind, Request $request): void
    {
        if (! in_array($kind, ['view', 'contact'], true) || ! $listing->user_id
            || (int) $listing->user_id === (int) $request->user()?->getKey()
            || $listing->trashed() || $listing->statusValue() !== 'active'
            || ($listing->expires_at !== null && $listing->expires_at->lte(now()))
            || ! $request->hasSession() || ! app(OperationsSettings::class)->telemetry_enabled) {
            return;
        }
        $day = CarbonImmutable::now(config('app.timezone'))->toDateString();
        $visitor = $request->user() ? 'user:'.$request->user()->getKey() : 'session:'.$request->session()->getId();
        $attributes = [
            'listing_id' => $listing->getKey(), 'owner_id' => $listing->user_id,
            'kind' => $kind, 'event_day' => $day,
            'visitor_hash' => hash_hmac('sha256', $listing->getKey().'|'.$listing->user_id.'|'.$day.'|'.$visitor, (string) config('app.key')),
        ];
        self::query()->firstOrCreate($attributes);
    }

    public static function reportFor(Listing $listing, CarbonImmutable $start, CarbonImmutable $end): array
    {
        $buckets = self::query()->where('listing_id', $listing->getKey())->where('owner_id', $listing->user_id)
            ->whereBetween('event_day', [$start->toDateString(), $end->toDateString()])
            ->selectRaw('event_day, kind, COUNT(*) as aggregate')->groupBy('event_day', 'kind')->get();
        $rows = [];
        for ($day = $start; $day->lte($end); $day = $day->addDay()) {
            $rows[$day->toDateString()] = ['date' => $day, 'view' => null, 'contact' => null];
        }
        foreach ($buckets as $bucket) {
            $date = $bucket->event_day->toDateString();
            if (isset($rows[$date]) && in_array($bucket->kind, ['view', 'contact'], true)) {
                $rows[$date][$bucket->kind] = (int) $bucket->aggregate;
            }
        }

        return ['days' => array_values($rows), 'views' => array_sum(array_column($rows, 'view')), 'contacts' => array_sum(array_column($rows, 'contact'))];
    }
}
