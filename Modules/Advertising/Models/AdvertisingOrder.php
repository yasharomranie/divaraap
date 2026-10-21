<?php

declare(strict_types=1);

namespace Modules\Advertising\Models;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

final class AdvertisingOrder extends Model
{
    use LogsActivity;
    use SoftDeletes;

    protected $table = 'advertising_orders';

    protected $guarded = ['id'];

    private bool $writing = false;

    protected function casts(): array
    {
        return ['duration_days' => 'integer', 'requested_budget' => 'integer', 'quoted_at' => 'datetime', 'accepted_at' => 'datetime', 'completed_at' => 'datetime'];
    }

    protected static function booted(): void
    {
        self::saving(function (self $order): void {
            if (! $order->writing) {
                throw ValidationException::withMessages(['order' => __('advertising::orders.invalid_state')]);
            }
        });
        self::deleting(fn () => throw ValidationException::withMessages(['order' => __('advertising::orders.immutable')]));
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

    public static function assertActive(): void
    {
        abort_unless(auth()->check() && (string) auth()->user()->status === 'active', 403);
    }

    public static function placementOptions(): array
    {
        return BannerPlacement::query()->where('is_active', true)->where('key', '!=', 'category-top')->whereIn('key', BannerPlacement::KEYS)->orderBy('id')->pluck('key')
            ->mapWithKeys(fn (string $key): array => [$key => __('advertising::admin.'.$key)])->all();
    }

    public static function requestRules(): array
    {
        return [
            'request_key' => ['required', 'uuid'], 'title' => ['required', 'string', 'max:120'],
            'brief' => ['required', 'string', 'max:2000'], 'goal' => ['required', Rule::in(['awareness', 'traffic', 'conversation'])],
            'placement' => ['required', Rule::in(array_keys(self::placementOptions()))],
            'duration_days' => ['required', 'integer', 'min:1', 'max:90'],
            'requested_budget' => ['nullable', 'integer', 'min:1', 'max:1000000000000'],
        ];
    }

    public static function submit(array $input): self
    {
        self::assertActive();
        $data = Validator::make($input, self::requestRules())->validate();
        $data['title'] = trim($data['title']);
        $data['brief'] = trim($data['brief']);
        $data['request_key'] = strtolower($data['request_key']);
        $data['duration_days'] = (int) $data['duration_days'];
        $data['requested_budget'] = filled($data['requested_budget'] ?? null) ? (int) $data['requested_budget'] : null;
        if ($data['title'] === '' || $data['brief'] === '') {
            throw ValidationException::withMessages(['title' => __('advertising::orders.required_text')]);
        }
        $find = fn () => self::withTrashed()->where('user_id', auth()->id())->where('request_key', $data['request_key'])->first();
        $match = function (self $existing) use ($data): self {
            foreach ($data as $key => $value) {
                if ($existing->trashed() || $value !== $existing->$key) {
                    throw ValidationException::withMessages(['request_key' => __('advertising::orders.replay_conflict')]);
                }
            }

            return $existing;
        };
        if ($existing = $find()) {
            return $match($existing);
        }
        try {
            return DB::transaction(function () use ($data): self {
                $order = new self($data + ['user_id' => auth()->id(), 'state' => 'submitted']);
                $order->persist();

                return $order;
            });
        } catch (QueryException $exception) {
            if ($existing = $find()) {
                return $match($existing);
            }
            throw $exception;
        }
    }

    public static function forCustomer(int $userId): LengthAwarePaginator
    {
        return self::query()->where('user_id', $userId)->with('invoice')->latest('id')->paginate(15);
    }

    public static function accessible(string $id): self
    {
        self::assertActive();

        return self::query()->when(! AdvertisingInvoice::billingAdmin(), fn ($query) => $query->where('user_id', auth()->id()))->with(['invoice.payments', 'reservations.placement'])->findOrFail($id);
    }

    public function reservations(): HasMany
    {
        return $this->hasMany(PlacementReservation::class, 'order_id')->select(['id', 'order_id', 'placement_id', 'title', 'device', 'starts_at', 'ends_at', 'state', 'cancel_reason'])->orderBy('starts_at');
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(AdvertisingInvoice::class, 'invoice_id');
    }

    private function requireState(array $states): void
    {
        if (! in_array($this->state, $states, true)) {
            throw ValidationException::withMessages(['order' => __('advertising::orders.invalid_state')]);
        }
    }

    public function quote(string $amount, string $terms, ?int $campaignId = null): void
    {
        abort_unless(AdvertisingInvoice::billingAdmin(), 403);
        $terms = trim($terms);
        if ($terms === '' || mb_strlen($terms) > 1000) {
            throw ValidationException::withMessages(['terms' => __('advertising::orders.required_terms')]);
        }
        DB::transaction(function () use ($amount, $terms, $campaignId): void {
            $order = self::query()->lockForUpdate()->findOrFail($this->id);
            if ($order->state === 'quoted' && (string) $order->invoice?->amount === $amount && $order->manager_note === $terms && $order->campaign_id === $campaignId) {
                return;
            }
            $order->requireState(['submitted']);
            $invoice = AdvertisingInvoice::create(['user_id' => $order->user_id, 'campaign_id' => $campaignId, 'description' => Str::limit($order->title, 120).' #'.$order->id, 'amount' => $amount]);
            $order->forceFill(['state' => 'quoted', 'invoice_id' => $invoice->id, 'campaign_id' => $campaignId, 'manager_note' => $terms, 'reviewed_by' => auth()->id(), 'quoted_at' => now()])->persist();
        });
        $this->refresh();
    }

    public function customerAction(string $action): void
    {
        self::assertActive();
        DB::transaction(function () use ($action): void {
            $order = self::query()->where('user_id', auth()->id())->lockForUpdate()->findOrFail($this->id);
            if ($action === 'accept') {
                if ($order->state === 'accepted') {
                    return;
                }
                $order->requireState(['quoted']);
                $invoice = AdvertisingInvoice::query()->lockForUpdate()->findOrFail($order->invoice_id);
                if ($invoice->voided_at || (int) $invoice->user_id !== (int) $order->user_id) {
                    throw ValidationException::withMessages(['order' => __('advertising::orders.invalid_invoice')]);
                }
                $order->forceFill(['state' => 'accepted', 'accepted_at' => now()])->persist();
            } elseif ($action === 'cancel') {
                if ($order->state === 'cancelled') {
                    return;
                }
                $order->requireState(['submitted']);
                $order->forceFill(['state' => 'cancelled'])->persist();
            } else {
                throw ValidationException::withMessages(['order' => __('advertising::orders.invalid_state')]);
            }
        });
        $this->refresh();
    }

    public function reject(string $reason): void
    {
        abort_unless(AdvertisingInvoice::billingAdmin(), 403);
        if (trim($reason) === '' || mb_strlen($reason) > 500) {
            throw ValidationException::withMessages(['reason' => __('advertising::billing.reason_required')]);
        }
        DB::transaction(function () use ($reason): void {
            $order = self::query()->lockForUpdate()->findOrFail($this->id);
            $order->requireState(['submitted', 'quoted', 'accepted']);
            if ($order->invoice_id) {
                $invoice = AdvertisingInvoice::query()->lockForUpdate()->findOrFail($order->invoice_id);
                if (! $invoice->voided_at) {
                    $invoice->voidInvoice($reason);
                }
            }
            $order->forceFill(['state' => 'rejected', 'rejection_reason' => trim($reason), 'reviewed_by' => auth()->id()])->persist();
        });
        $this->refresh();
    }

    public function complete(int $campaignId): void
    {
        abort_unless(AdvertisingInvoice::billingAdmin(), 403);
        DB::transaction(function () use ($campaignId): void {
            $order = self::query()->lockForUpdate()->findOrFail($this->id);
            $order->requireState(['accepted']);
            $invoice = AdvertisingInvoice::query()->lockForUpdate()->findOrFail($order->invoice_id);
            if ($invoice->voided_at || $invoice->billingState() !== 'paid' || ! Campaign::query()->whereKey($campaignId)->exists() || ($order->campaign_id && (int) $order->campaign_id !== $campaignId)) {
                throw ValidationException::withMessages(['campaign_id' => __('advertising::orders.complete_invalid')]);
            }
            $order->forceFill(['state' => 'completed', 'campaign_id' => $campaignId, 'completed_at' => now(), 'reviewed_by' => auth()->id()])->persist();
        });
        $this->refresh();
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logOnly(['user_id', 'title', 'brief', 'goal', 'placement', 'duration_days', 'requested_budget', 'state', 'invoice_id', 'campaign_id', 'manager_note', 'rejection_reason', 'reviewed_by', 'quoted_at', 'accepted_at', 'completed_at'])->logOnlyDirty()->dontLogEmptyChanges();
    }
}
