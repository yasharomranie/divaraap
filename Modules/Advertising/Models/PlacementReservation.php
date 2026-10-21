<?php

declare(strict_types=1);

namespace Modules\Advertising\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

final class PlacementReservation extends Model
{
    use LogsActivity;
    use SoftDeletes;

    protected $table = 'advertising_reservations';

    protected $guarded = ['id'];

    private bool $writing = false;

    protected function casts(): array
    {
        return ['placement_id' => 'integer', 'order_id' => 'integer', 'starts_at' => 'datetime', 'ends_at' => 'datetime', 'cancelled_at' => 'datetime'];
    }

    protected static function booted(): void
    {
        self::saving(function (self $reservation): void {
            if (! $reservation->writing) {
                throw ValidationException::withMessages(['reservation' => __('advertising::reservations.immutable')]);
            }
        });
        self::deleting(fn () => throw ValidationException::withMessages(['reservation' => __('advertising::reservations.immutable')]));
    }

    private function persist(): void
    {
        $this->writing = true;
        try {
            $this->save();
        } finally {
            $this->writing = false;
        }
    }

    public static function placements(): array
    {
        return BannerPlacement::query()->where('is_active', true)->whereIn('key', BannerPlacement::KEYS)->orderBy('id')->get(['id', 'key'])
            ->mapWithKeys(fn (BannerPlacement $placement): array => [$placement->id => __('advertising::admin.'.$placement->key)])->all();
    }

    public static function orderOptions(): array
    {
        return AdvertisingOrder::query()->where('state', 'accepted')->latest('id')->limit(100)->get(['id', 'title'])->mapWithKeys(fn (AdvertisingOrder $order): array => [$order->id => '#'.$order->id.' '.$order->title])->all();
    }

    public function placement(): BelongsTo
    {
        return $this->belongsTo(BannerPlacement::class, 'placement_id');
    }

    public static function reserve(array $input): self
    {
        abort_unless(AdvertisingInvoice::billingAdmin(), 403);
        $data = Validator::make($input, [
            'request_key' => ['required', 'uuid'], 'placement_id' => ['required', 'integer', 'min:1'],
            'order_id' => ['nullable', 'integer', 'min:1'], 'title' => ['required', 'string', 'max:120'],
            'device' => ['required', Rule::in(['both', 'mobile', 'desktop'])],
            'starts_at' => ['required', 'date'], 'ends_at' => ['required', 'date', 'after:starts_at'],
            'note' => ['nullable', 'string', 'max:500'],
        ])->validate();
        $start = Carbon::parse($data['starts_at'])->utc();
        $end = Carbon::parse($data['ends_at'])->utc();
        if ($start->lessThan(now()) || $start->greaterThan(now()->addYear()) || $end->greaterThan($start->copy()->addDays(90)) || $start->second !== 0 || $end->second !== 0) {
            throw ValidationException::withMessages(['starts_at' => __('advertising::reservations.invalid_window')]);
        }
        $data['starts_at'] = $start->format('Y-m-d H:i:s');
        $data['ends_at'] = $end->format('Y-m-d H:i:s');
        $data['request_key'] = strtolower($data['request_key']);
        $data['placement_id'] = (int) $data['placement_id'];
        $data['order_id'] = filled($data['order_id'] ?? null) ? (int) $data['order_id'] : null;
        $data['note'] = trim($data['note'] ?? '') ?: null;
        $data['title'] = trim($data['title']);
        if ($data['title'] === '') {
            throw ValidationException::withMessages(['title' => __('advertising::orders.required_text')]);
        }

        return DB::transaction(function () use ($data, $start, $end): self {
            $placement = BannerPlacement::query()->lockForUpdate()->findOrFail($data['placement_id']);
            $existing = self::withTrashed()->where('request_key', $data['request_key'])->first();
            if ($existing) {
                foreach ($data as $key => $value) {
                    $stored = in_array($key, ['starts_at', 'ends_at'], true) ? $existing->$key->utc()->format('Y-m-d H:i:s') : $existing->$key;
                    if ($existing->trashed() || $stored !== $value) {
                        throw ValidationException::withMessages(['request_key' => __('advertising::orders.replay_conflict')]);
                    }
                }

                return $existing;
            }
            if (! $placement->is_active || ! in_array($placement->key, BannerPlacement::KEYS, true) || ($placement->device !== 'both' && $placement->device !== $data['device'])) {
                throw ValidationException::withMessages(['placement_id' => __('advertising::reservations.invalid_placement')]);
            }
            if ($data['order_id']) {
                $order = AdvertisingOrder::query()->lockForUpdate()->findOrFail($data['order_id']);
                $invoice = AdvertisingInvoice::query()->lockForUpdate()->find($order->invoice_id);
                if ($order->state !== 'accepted' || $order->placement !== $placement->key || ! $invoice || $invoice->voided_at || $invoice->billingState() !== 'paid') {
                    throw ValidationException::withMessages(['order_id' => __('advertising::reservations.invalid_order')]);
                }
            }
            $conflict = self::query()->where('placement_id', $placement->id)->where('state', 'confirmed')->where('starts_at', '<', $end)->where('ends_at', '>', $start)
                ->when($data['device'] !== 'both', fn ($query) => $query->whereIn('device', ['both', $data['device']]))->lockForUpdate()->first(['id']);
            if ($conflict) {
                throw ValidationException::withMessages(['starts_at' => __('advertising::reservations.conflict')]);
            }
            $reservation = new self($data + ['state' => 'confirmed', 'created_by' => auth()->id()]);
            $reservation->persist();

            return $reservation;
        });
    }

    public function cancel(string $reason): void
    {
        abort_unless(AdvertisingInvoice::billingAdmin(), 403);
        if (trim($reason) === '' || mb_strlen($reason) > 500) {
            throw ValidationException::withMessages(['reason' => __('advertising::billing.reason_required')]);
        }
        DB::transaction(function () use ($reason): void {
            BannerPlacement::withTrashed()->lockForUpdate()->findOrFail($this->placement_id);
            $reservation = self::query()->lockForUpdate()->findOrFail($this->id);
            if ($reservation->state !== 'confirmed' || $reservation->ends_at->lessThanOrEqualTo(now())) {
                throw ValidationException::withMessages(['reservation' => __('advertising::reservations.cannot_cancel')]);
            }
            $reservation->forceFill(['state' => 'cancelled', 'cancelled_at' => now(), 'cancelled_by' => auth()->id(), 'cancel_reason' => trim($reason)])->persist();
        });
        $this->refresh();
    }

    public function displayState(): string
    {
        return $this->state === 'cancelled' ? 'cancelled' : ($this->ends_at->lessThanOrEqualTo(now()) ? 'ended' : ($this->starts_at->isFuture() ? 'upcoming' : 'current'));
    }

    public static function calendar(string $month, string $placementId = ''): array
    {
        abort_unless(AdvertisingInvoice::billingAdmin(), 403);
        Validator::make(['month' => $month, 'placementId' => $placementId], ['month' => ['required', 'date_format:Y-m', 'regex:/^(20[0-9]{2})-(0[1-9]|1[0-2])$/D'], 'placementId' => ['nullable', 'integer', 'min:1']])->validate();
        $first = Carbon::createFromFormat('!Y-m', $month, 'Asia/Tehran');
        $until = $first->copy()->addMonth();
        $items = self::query()->with('placement')->where('state', 'confirmed')->where('starts_at', '<', $until->copy()->utc())->where('ends_at', '>', $first->copy()->utc())
            ->when($placementId !== '', fn ($query) => $query->where('placement_id', (int) $placementId))->orderBy('starts_at')->limit(1001)->get();
        $limited = $items->count() > 1000;
        $items = $items->take(1000);
        $days = [];
        for ($day = $first->copy(); $day->lessThan($until); $day->addDay()) {
            $end = $day->copy()->addDay();
            $days[] = ['date' => $day->format('Y-m-d'), 'items' => $items->filter(fn (self $item): bool => $item->starts_at->lessThan($end) && $item->ends_at->greaterThan($day))->values()];
        }

        return ['days' => $days, 'limited' => $limited];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logOnly(['placement_id', 'order_id', 'title', 'device', 'starts_at', 'ends_at', 'state', 'created_by', 'cancelled_by', 'cancelled_at', 'cancel_reason', 'note'])->logOnlyDirty()->dontLogEmptyChanges();
    }
}
