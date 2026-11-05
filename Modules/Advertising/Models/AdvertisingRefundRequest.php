<?php

declare(strict_types=1);

namespace Modules\Advertising\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

final class AdvertisingRefundRequest extends Model
{
    use LogsActivity;
    use SoftDeletes;

    protected $guarded = [];

    protected $hidden = ['reason', 'decision_reason', 'form_key'];

    private bool $writing = false;

    protected function casts(): array
    {
        return ['amount' => 'integer', 'version' => 'integer', 'reason' => 'encrypted', 'decision_reason' => 'encrypted', 'reviewed_at' => 'datetime', 'completed_at' => 'datetime'];
    }

    protected static function booted(): void
    {
        self::saving(function (self $request): void {
            if (! $request->writing) {
                throw ValidationException::withMessages(['refund' => __('advertising::refunds.immutable')]);
            }
        });
        self::deleting(fn () => throw ValidationException::withMessages(['refund' => __('advertising::refunds.immutable')]));
    }

    public static function requestFor(AdvertisingPayment $payment, string $amount, string $reason, string $formKey): self
    {
        abort_unless(auth()->check() && (string) auth()->user()->status === 'active', 403);
        AdvertisingPaymentAdjustment::validateAmount($amount);
        $reason = AdvertisingPaymentAdjustment::validateReason($reason);
        if (! Str::isUuid($formKey)) {
            throw ValidationException::withMessages(['form_key' => __('advertising::refunds.invalid_key')]);
        }

        try {
            return DB::transaction(function () use ($payment, $amount, $reason, $formKey): self {
                $invoice = AdvertisingInvoice::query()->whereKey($payment->invoice_id)->lockForUpdate()->firstOrFail();
                abort_unless(AdvertisingInvoice::billingAdmin() || (int) $invoice->user_id === (int) auth()->id(), 404);
                $source = AdvertisingPayment::query()->whereKey($payment->id)->where('invoice_id', $invoice->id)->lockForUpdate()->firstOrFail();
                $existing = self::withTrashed()->where('form_key', $formKey)->lockForUpdate()->first();
                if ($existing) {
                    if ($existing->trashed() || (int) $existing->payment_id !== (int) $source->id || (int) $existing->requested_by !== (int) auth()->id() || $existing->amount !== (int) $amount || ! hash_equals($existing->reason, $reason)) {
                        throw ValidationException::withMessages(['form_key' => __('advertising::refunds.replay_conflict')]);
                    }

                    return $existing;
                }
                if (OnlinePaymentAttempt::unresolved($invoice->id)->exists()) {
                    throw ValidationException::withMessages(['refund' => __('advertising::gateway.in_progress')]);
                }
                if (self::query()->where('payment_id', $source->id)->whereIn('state', ['requested', 'approved'])->lockForUpdate()->first() !== null
                    || (int) $amount > AdvertisingPaymentAdjustment::availableForPayment($source)
                    || ! AdvertisingInvoice::billingAdmin() && self::withTrashed()->where('payment_id', $source->id)->count() >= 100) {
                    throw ValidationException::withMessages(['amount' => __('advertising::refunds.exceeds_available')]);
                }
                $request = new self(['invoice_id' => $invoice->id, 'payment_id' => $source->id, 'user_id' => $invoice->user_id, 'requested_by' => auth()->id(), 'amount' => (int) $amount, 'reason' => $reason, 'form_key' => $formKey, 'state' => 'requested', 'version' => 0]);
                $request->persist();

                return $request;
            }, 3);
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages(['form_key' => __('advertising::refunds.replay_conflict')]);
        }
    }

    public function decide(string $action, int $version, ?string $reason = null, bool $confirmedNoTransfer = false): self
    {
        abort_unless(AdvertisingInvoice::billingAdmin(), 403);
        if (! in_array($action, ['approve', 'reject', 'cancel'], true)) {
            abort(422);
        }
        $decision = $action === 'approve' ? null : AdvertisingPaymentAdjustment::validateReason($reason ?? '');

        return DB::transaction(function () use ($action, $version, $decision, $confirmedNoTransfer): self {
            AdvertisingInvoice::query()->whereKey($this->invoice_id)->lockForUpdate()->firstOrFail();
            $request = self::query()->whereKey($this->id)->lockForUpdate()->firstOrFail();
            $expected = $action === 'cancel' ? 'approved' : 'requested';
            if ($request->version !== $version || $request->state !== $expected || $action === 'cancel' && ! $confirmedNoTransfer) {
                throw ValidationException::withMessages(['refund' => __('advertising::refunds.stale')]);
            }
            $request->state = match ($action) {
                'approve' => 'approved', 'reject' => 'rejected', 'cancel' => 'cancelled'
            };
            $request->reviewed_by = auth()->id();
            $request->reviewed_at = now();
            $request->decision_reason = $decision;
            $request->version++;
            $request->persist();

            return $request;
        }, 3);
    }

    public function withdraw(int $version): self
    {
        abort_unless(auth()->check() && (string) auth()->user()->status === 'active', 403);

        return DB::transaction(function () use ($version): self {
            AdvertisingInvoice::query()->whereKey($this->invoice_id)->lockForUpdate()->firstOrFail();
            $request = self::query()->whereKey($this->id)->where('user_id', auth()->id())->lockForUpdate()->firstOrFail();
            if ($request->version !== $version || $request->state !== 'requested') {
                throw ValidationException::withMessages(['refund' => __('advertising::refunds.stale')]);
            }
            $request->state = 'withdrawn';
            $request->version++;
            $request->persist();

            return $request;
        }, 3);
    }

    public function complete(string $reference, int $version, bool $transferConfirmed): self
    {
        abort_unless(AdvertisingInvoice::billingAdmin(), 403);
        $reference = strtoupper(trim($reference));
        if (! $transferConfirmed || ! preg_match('/\A[A-Z0-9][A-Z0-9_-]{3,79}\z/', $reference)) {
            throw ValidationException::withMessages(['reference' => __('advertising::refunds.transfer_required')]);
        }
        try {
            return DB::transaction(function () use ($reference, $version): self {
                AdvertisingInvoice::query()->whereKey($this->invoice_id)->lockForUpdate()->firstOrFail();
                $request = self::query()->whereKey($this->id)->lockForUpdate()->firstOrFail();
                $existing = AdvertisingPaymentAdjustment::withTrashed()->where('refund_request_id', $request->id)->lockForUpdate()->first();
                if ($existing) {
                    if ($existing->trashed() || $existing->reference !== $reference) {
                        throw ValidationException::withMessages(['reference' => __('advertising::refunds.replay_conflict')]);
                    }

                    return $request;
                }
                if ($request->version !== $version || $request->state !== 'approved') {
                    throw ValidationException::withMessages(['refund' => __('advertising::refunds.stale')]);
                }
                if (AdvertisingPaymentAdjustment::withTrashed()->where('kind', 'refund')->where('reference', $reference)->exists()) {
                    throw ValidationException::withMessages(['reference' => __('advertising::refunds.duplicate_reference')]);
                }
                $request->state = 'completed';
                $request->completed_by = auth()->id();
                $request->completed_at = now();
                $request->version++;
                $request->persist();
                AdvertisingPaymentAdjustment::completeRefund($request, $reference);

                return $request;
            }, 3);
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages(['reference' => __('advertising::refunds.duplicate_reference')]);
        }
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logOnly(['invoice_id', 'payment_id', 'user_id', 'requested_by', 'amount', 'state', 'version', 'reviewed_by', 'reviewed_at', 'completed_by', 'completed_at'])->logOnlyDirty()->dontLogEmptyChanges();
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
}
