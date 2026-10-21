<?php

declare(strict_types=1);

namespace Modules\Advertising\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Modules\Advertising\Support\AdvertisingFrequency;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

final class AdvertisingExperiment extends Model
{
    use LogsActivity;
    use SoftDeletes;

    protected $fillable = ['name', 'placement_id', 'banner_a_id', 'banner_b_id', 'duration_days'];

    private bool $transitioning = false;

    protected function casts(): array
    {
        return ['snapshot' => 'array', 'started_at' => 'datetime', 'ends_at' => 'datetime', 'stopped_at' => 'datetime', 'duration_days' => 'integer'];
    }

    protected static function booted(): void
    {
        self::saving(function (self $experiment): void {
            abort_unless(AdvertisingInvoice::billingAdmin(), 403);
            if ($experiment->exists && ! $experiment->transitioning) {
                self::invalid();
            }
            if (! $experiment->exists) {
                Validator::make($experiment->getAttributes(), [
                    'name' => ['required', 'string', 'max:120'], 'placement_id' => ['required', 'integer'],
                    'banner_a_id' => ['required', 'integer', 'different:banner_b_id'], 'banner_b_id' => ['required', 'integer'],
                    'duration_days' => ['required', 'integer', 'min:1', 'max:30'],
                ])->validate();
                $experiment->state = 'draft';
                $experiment->snapshot = null;
                $experiment->started_at = $experiment->ends_at = $experiment->stopped_at = null;
                if (trim($experiment->name) === '' || ! $experiment->pair()) {
                    self::invalid();
                }
            }
        });
        self::deleting(fn () => self::invalid());
    }

    private static function invalid(): never
    {
        throw ValidationException::withMessages(['banner_b_id' => __('advertising::experiments.invalid')]);
    }

    public static function fingerprint(Banner $banner): string
    {
        $attributes = $banner->getRawOriginal();
        unset($attributes['created_at'], $attributes['updated_at']);
        ksort($attributes);

        return hash('sha256', json_encode($attributes, JSON_THROW_ON_ERROR));
    }

    private static function campaignSignature(Banner $banner): string
    {
        $attributes = $banner->campaign?->getRawOriginal() ?? [];
        foreach (['created_at', 'updated_at', 'recorded_impressions', 'recorded_clicks', 'recorded_cost'] as $field) {
            unset($attributes[$field]);
        }
        ksort($attributes);

        return hash('sha256', json_encode($attributes, JSON_THROW_ON_ERROR));
    }

    public function pair(?array $context = null): ?array
    {
        $placement = BannerPlacement::query()->whereKey($this->placement_id)->where('is_active', true)->first();
        if (! $placement || $placement->device !== 'both' || $placement->key === 'category-top') {
            return null;
        }
        $banners = Banner::query()->available()->with('campaign')->whereIn('id', [$this->banner_a_id, $this->banner_b_id])->get()->keyBy('id');
        $a = $banners->get($this->banner_a_id);
        $b = $banners->get($this->banner_b_id);
        if (! $a || ! $b || $a->id === $b->id || $a->campaign_id !== $b->campaign_id || $a->destination() !== $b->destination() || ! $a->destination()) {
            return null;
        }
        if ($a->campaign_id !== null && (! $a->campaign || $a->campaign->deliveryState() !== 'running')) {
            return null;
        }
        $assigned = $placement->assignments()->whereIn('banner_id', [$a->id, $b->id])->reorder()->distinct()->pluck('banner_id')->all();
        if (count($assigned) !== 2) {
            return null;
        }
        foreach ([$a, $b] as $banner) {
            if ($banner->creative_format !== 'image' || $banner->device !== 'both' || ! filled($banner->image) || $banner->daily_frequency_cap !== null || ! Storage::disk('public')->exists($banner->image) || ($context !== null && ! $banner->eligibleForCampaign($context))) {
                return null;
            }
        }

        return ['A' => $a, 'B' => $b];
    }

    public function start(): void
    {
        abort_unless(AdvertisingInvoice::billingAdmin(), 403);
        DB::transaction(function (): void {
            BannerPlacement::query()->whereKey(self::query()->whereKey($this->id)->value('placement_id'))->lockForUpdate()->firstOrFail();
            $experiment = self::query()->lockForUpdate()->findOrFail($this->id);
            if ($experiment->state === 'running' && $experiment->ends_at->isFuture()) {
                return;
            }
            if ($experiment->state !== 'draft' || self::query()->where('placement_id', $experiment->placement_id)->where('state', 'running')->where('ends_at', '>', now())->lockForUpdate()->exists()) {
                self::invalid();
            }
            $pair = $experiment->pair();
            if (! $pair) {
                self::invalid();
            }
            $experiment->transitioning = true;
            $experiment->forceFill(['state' => 'running', 'snapshot' => array_map(fn (Banner $banner): array => ['id' => $banner->id, 'title' => $banner->title, 'signature' => self::fingerprint($banner), 'campaign' => self::campaignSignature($banner)], $pair), 'started_at' => now(), 'ends_at' => now()->addDays($experiment->duration_days)])->save();
        });
        $this->refresh();
    }

    public function stop(): void
    {
        abort_unless(AdvertisingInvoice::billingAdmin(), 403);
        DB::transaction(function (): void {
            $experiment = self::query()->lockForUpdate()->findOrFail($this->id);
            if ($experiment->state === 'stopped') {
                return;
            }
            if ($experiment->state !== 'running') {
                self::invalid();
            }
            $experiment->transitioning = true;
            $experiment->forceFill(['state' => 'stopped', 'stopped_at' => now()])->save();
        });
        $this->refresh();
    }

    public function statusLabel(): string
    {
        if ($this->state === 'running' && $this->ends_at?->isFuture() && ! $this->unchangedPair()) {
            return __('advertising::experiments.blocked');
        }

        return __('advertising::experiments.'.($this->state === 'running' && $this->ends_at?->isPast() ? 'ended' : $this->state));
    }

    public static function arm(int $id, string $session): string
    {
        return hexdec(substr(hash_hmac('sha256', $id.'|'.$session, config('app.key')), 0, 8)) % 2 === 0 ? 'A' : 'B';
    }

    private function unchangedPair(?array $context = null): ?array
    {
        $pair = $this->pair($context);
        if (! $pair) {
            return null;
        }
        foreach ($pair as $arm => $banner) {
            if (($this->snapshot[$arm]['signature'] ?? '') !== self::fingerprint($banner) || ($this->snapshot[$arm]['campaign'] ?? '') !== self::campaignSignature($banner)) {
                return null;
            }
        }

        return $pair;
    }

    public static function delivery(BannerPlacement $placement, array $context): ?array
    {
        $experiment = self::query()->where('placement_id', $placement->id)->where('state', 'running')->where('ends_at', '>', now())->first();
        if (! $experiment) {
            return null;
        }
        if (! request()->hasSession() || ! ($pair = $experiment->unchangedPair($context))) {
            return [];
        }
        $arm = self::arm($experiment->id, request()->session()->getId());
        $banner = $pair[$arm];
        if (! app(AdvertisingFrequency::class)->allows($banner)) {
            return [];
        }
        $banner->experimentExposure = ['id' => $experiment->id, 'arm' => $arm, 'signature' => $experiment->snapshot[$arm]['signature']];

        return ['both' => $banner];
    }

    public static function accepts(array $exposure, Banner $banner, string $placement): bool
    {
        $experiment = self::query()->whereKey($exposure['id'] ?? 0)->where('state', 'running')->where('ends_at', '>', now())->first();
        $arm = $exposure['arm'] ?? '';
        if (! $experiment || ! in_array($arm, ['A', 'B'], true) || self::arm($experiment->id, request()->session()->getId()) !== $arm) {
            return false;
        }
        $snapshot = $experiment->snapshot[$arm] ?? [];

        return ($snapshot['id'] ?? null) === $banner->id && ($snapshot['signature'] ?? '') === ($exposure['signature'] ?? null)
            && ($snapshot['signature'] ?? '') === self::fingerprint($banner)
            && ($snapshot['campaign'] ?? '') === self::campaignSignature($banner)
            && BannerPlacement::query()->whereKey($experiment->placement_id)->where('key', $placement)->exists();
    }

    public function report(): array
    {
        abort_unless(AdvertisingInvoice::billingAdmin(), 403);
        $rows = AdvertisingEvent::query()->where('experiment_id', $this->id)->select('experiment_arm')
            ->selectRaw("SUM(CASE WHEN kind = 'impression' THEN 1 ELSE 0 END) AS impressions")
            ->selectRaw("SUM(CASE WHEN kind = 'click' THEN 1 ELSE 0 END) AS clicks")
            ->selectRaw("SUM(CASE WHEN kind = 'conversion' THEN 1 ELSE 0 END) AS conversations")
            ->groupBy('experiment_arm')->get()->keyBy('experiment_arm');
        $result = [];
        foreach (['A', 'B'] as $arm) {
            $row = $rows->get($arm);
            $impressions = (int) ($row?->impressions ?? 0);
            $clicks = (int) ($row?->clicks ?? 0);
            $result[$arm] = ['title' => $this->snapshot[$arm]['title'] ?? $arm, 'impressions' => $impressions, 'clicks' => $clicks, 'conversations' => (int) ($row?->conversations ?? 0), 'ctr' => $impressions > 0 ? round(100 * $clicks / $impressions, 2) : null];
        }

        return $result;
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logOnly(['name', 'placement_id', 'banner_a_id', 'banner_b_id', 'duration_days', 'state', 'snapshot', 'started_at', 'ends_at', 'stopped_at'])->logOnlyDirty()->dontLogEmptyChanges();
    }
}
