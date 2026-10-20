<?php

declare(strict_types=1);

namespace Modules\Advertising\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\DB;
use Modules\Admin\App\Settings\OperationsSettings;
use Modules\Advertising\Support\AdvertisingFrequency;
use Modules\Advertising\Support\AdvertisingToken;
use Modules\Listing\Models\Listing;

final class AdvertisingEvent extends Model
{
    use SoftDeletes;

    protected $table = 'advertising_events';

    protected $guarded = ['id'];

    public static function record(string $token, string $kind, string $device, ?int $listingId = null): void
    {
        if (! app(OperationsSettings::class)->telemetry_enabled || ! in_array($kind, ['impression', 'click'], true) || ! in_array($device, ['mobile', 'desktop'], true)) {
            return;
        }
        $data = AdvertisingToken::read($token);
        if (! $data || ! in_array($data['device'], ['both', $device], true) || ($kind === 'impression' && now()->timestamp - $data['issued'] < 1)) {
            return;
        }
        $banner = Banner::query()->available()->with('campaign')->find($data['banner']);
        if (! $banner || $banner->campaign_id !== $data['campaign'] || ! $banner->eligibleForCampaign($data['context'], forDelivery: false)) {
            return;
        }
        if (($banner->creative_format === 'service' || ($data['service'] ?? null) !== null)
            && ($banner->creative_format !== 'service' || ($data['service'] ?? null) !== (int) $banner->service_listing_id)) {
            return;
        }
        $placement = BannerPlacement::query()->where('key', $data['placement'])->where('is_active', true)->first();
        if (! $placement || ! in_array($placement->device, ['both', $device], true) || ! in_array($banner->device, ['both', $device], true) || ! $placement->assignments()->where('banner_id', $banner->id)->exists()) {
            return;
        }
        if (($data['visitor'] ?? null) !== null && ! hash_equals($data['visitor'], app(AdvertisingFrequency::class)->visitor())) {
            return;
        }
        $targetListingId = null;
        if ($kind === 'click') {
            if ($banner->creative_format === 'service') {
                if ($listingId !== null) {
                    return;
                }
                $targetListingId = (int) $banner->service_listing_id;
            } elseif ($banner->creative_format === 'collection') {
                if (! $listingId || ! in_array($listingId, $data['listings'] ?? [], true) || ! isset($banner->advertisedListings()[$listingId])) {
                    return;
                }
                $targetListingId = $listingId;
            } else {
                if ($listingId !== null || ! $banner->destination()) {
                    return;
                }
                $targetListingId = Listing::advertisingDestinationId((string) $banner->destination());
            }
        }
        $receipt = hash('sha256', $data['nonce'].'|'.$kind);
        DB::transaction(function () use ($banner, $data, $device, $kind, $receipt, $targetListingId): void {
            $inserted = self::query()->insertOrIgnore([
                'banner_id' => $banner->id, 'campaign_id' => $data['campaign'],
                'placement' => $data['placement'], 'device' => $device, 'kind' => $kind,
                'receipt' => $receipt, 'target_listing_id' => $targetListingId,
                'event_date' => now()->toDateString(), 'created_at' => now(), 'updated_at' => now(),
            ]);
            if ($inserted && $data['campaign'] !== null) {
                Campaign::recordDelivery((int) $data['campaign'], $kind, $data['pricing'] ?? []);
            }
            if ($inserted && $kind === 'impression' && ($data['visitor'] ?? null) !== null) {
                FrequencyCount::record($data['visitor'], $data['frequency'] ?? []);
            }
        });
        if ($kind === 'click') {
            $click = self::query()->where('receipt', $receipt)->first();
            $previous = json_decode((string) request()->cookie('advertising_attribution', ''), true);
            if ($click && $targetListingId && (int) $click->target_listing_id === $targetListingId && (! is_array($previous) || $click->id > ($previous['id'] ?? 0))) {
                Cookie::queue(cookie('advertising_attribution', json_encode(['id' => $click->id, 'at' => $click->created_at->timestamp], JSON_THROW_ON_ERROR), 1440, '/', null, request()->isSecure(), true, false, 'lax'));
            }
        }
        if (Cache::add('advertising-event-retention', true, now()->addDay())) {
            self::withTrashed()->where('created_at', '<', now()->subDays(90))->forceDelete();
        }
    }

    public static function recordConversation(int $listingId): void
    {
        if (! request()->hasSession() || ! app(OperationsSettings::class)->telemetry_enabled) {
            return;
        }
        $source = json_decode((string) request()->cookie('advertising_attribution', ''), true);
        if (! is_array($source) || ! isset($source['id'], $source['at']) || $source['at'] < now()->subHours(24)->timestamp || $source['at'] > now()->timestamp) {
            return;
        }
        $click = self::query()->whereKey($source['id'])->where('kind', 'click')->where('target_listing_id', $listingId)->where('created_at', '>=', now()->subHours(24))->first();
        if (! $click) {
            return;
        }
        self::query()->insertOrIgnore([
            'banner_id' => $click->banner_id, 'campaign_id' => $click->campaign_id,
            'placement' => $click->placement, 'device' => $click->device, 'kind' => 'conversion',
            'receipt' => hash('sha256', 'conversation|'.$click->id),
            'target_listing_id' => $listingId, 'source_click_id' => $click->id,
            'event_date' => now()->toDateString(), 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    public static function report(?int $campaignId, string $dimension, ?string $from = null, ?string $to = null): array
    {
        $rows = [];
        foreach (self::reportRows($campaignId, $dimension, $from, $to) as $row) {
            $rows[] = $row;
            if (count($rows) >= 100) {
                break;
            }
        }

        return $rows;
    }

    public static function reportRows(?int $campaignId, string $dimension, ?string $from = null, ?string $to = null): \Generator
    {
        $field = in_array($dimension, ['banner_id', 'placement', 'device', 'event_date'], true) ? $dimension : 'event_date';
        $query = self::query()->whereBetween('event_date', [$from ?? now()->subDays(29)->toDateString(), $to ?? now()->toDateString()])
            ->when($campaignId, fn ($query) => $query->where('campaign_id', $campaignId))
            ->select($field)->selectRaw("SUM(CASE WHEN kind = 'impression' THEN 1 ELSE 0 END) AS impressions")
            ->selectRaw("SUM(CASE WHEN kind = 'click' THEN 1 ELSE 0 END) AS clicks")
            ->selectRaw("SUM(CASE WHEN kind = 'conversion' THEN 1 ELSE 0 END) AS conversations")
            ->groupBy($field)->orderBy($field, 'desc');
        foreach ($query->lazy(200)->chunk(200) as $chunk) {
            $names = $field === 'banner_id' ? Banner::withTrashed()->whereIn('id', $chunk->pluck('banner_id'))->pluck('title', 'id')->all() : [];
            foreach ($chunk as $row) {
                yield [
                    'label' => $names[$row->$field] ?? $row->$field,
                    'impressions' => (int) $row->impressions, 'clicks' => (int) $row->clicks,
                    'conversations' => (int) $row->conversations,
                    'ctr' => $row->impressions ? round(100 * $row->clicks / $row->impressions, 2) : null,
                ];
            }
        }
    }
}
