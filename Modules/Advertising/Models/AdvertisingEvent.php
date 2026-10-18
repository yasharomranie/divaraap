<?php

declare(strict_types=1);

namespace Modules\Advertising\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Cache;
use Modules\Admin\App\Settings\OperationsSettings;
use Modules\Advertising\Support\AdvertisingToken;

final class AdvertisingEvent extends Model
{
    use SoftDeletes;

    protected $table = 'advertising_events';

    protected $guarded = ['id'];

    public static function record(string $token, string $kind, string $device): void
    {
        if (! app(OperationsSettings::class)->telemetry_enabled || ! in_array($kind, ['impression', 'click'], true) || ! in_array($device, ['mobile', 'desktop'], true)) {
            return;
        }
        $data = AdvertisingToken::read($token);
        if (! $data || ! in_array($data['device'], ['both', $device], true) || ($kind === 'impression' && now()->timestamp - $data['issued'] < 1)) {
            return;
        }
        $banner = Banner::query()->available()->with('campaign')->find($data['banner']);
        if (! $banner || $banner->campaign_id !== $data['campaign'] || ! $banner->eligibleForCampaign($data['context']) || ($kind === 'click' && ! $banner->destination())) {
            return;
        }
        $placement = BannerPlacement::query()->where('key', $data['placement'])->where('is_active', true)->first();
        if (! $placement || ! in_array($placement->device, ['both', $device], true) || ! in_array($banner->device, ['both', $device], true) || ! $placement->assignments()->where('banner_id', $banner->id)->exists()) {
            return;
        }
        self::query()->insertOrIgnore([
            'banner_id' => $banner->id, 'campaign_id' => $data['campaign'],
            'placement' => $data['placement'], 'device' => $device, 'kind' => $kind,
            'receipt' => hash('sha256', $data['nonce'].'|'.$kind),
            'event_date' => now()->toDateString(), 'created_at' => now(), 'updated_at' => now(),
        ]);
        if (Cache::add('advertising-event-retention', true, now()->addDay())) {
            self::withTrashed()->where('created_at', '<', now()->subDays(90))->forceDelete();
        }
    }

    public static function report(?int $campaignId, string $dimension): array
    {
        $field = in_array($dimension, ['banner_id', 'placement', 'device', 'event_date'], true) ? $dimension : 'event_date';
        $rows = self::query()->where('event_date', '>=', now()->subDays(29)->toDateString())
            ->when($campaignId, fn ($query) => $query->where('campaign_id', $campaignId))
            ->select($field)->selectRaw("SUM(CASE WHEN kind = 'impression' THEN 1 ELSE 0 END) AS impressions")
            ->selectRaw("SUM(CASE WHEN kind = 'click' THEN 1 ELSE 0 END) AS clicks")
            ->groupBy($field)->orderBy($field, 'desc')->limit(100)->get();
        $names = $field === 'banner_id' ? Banner::withTrashed()->whereIn('id', $rows->pluck('banner_id'))->pluck('title', 'id')->all() : [];

        return $rows->map(fn ($row) => [
            'label' => $names[$row->$field] ?? $row->$field,
            'impressions' => (int) $row->impressions, 'clicks' => (int) $row->clicks,
            'ctr' => $row->impressions ? round(100 * $row->clicks / $row->impressions, 2) : null,
        ])->all();
    }
}
