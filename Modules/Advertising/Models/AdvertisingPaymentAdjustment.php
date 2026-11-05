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

final class AdvertisingPaymentAdjustment extends Model
{
    use LogsActivity;
    use SoftDeletes;

    protected $guarded = [];

    protected $hidden = ['reason', 'operation_key'];

    private bool $writing = false;

    protected function casts(): array
    {
        return ['amount' => 'integer', 'reason' => 'encrypted'];
    }

    protected static function booted(): void
    {
        self::saving(function (self $entry): void {
            if (! $entry->writing || $entry->exists) {
                throw ValidationException::withMessages(['adjustment' => __('advertising::refunds.immutable')]);
            }
        });
        self::deleting(fn () => throw ValidationException::withMessages(['adjustment' => __('advertising::refunds.immutable')]));
    }

    public static function correct(AdvertisingPayment $payment, string $amount, string $reason, string $operationKey, string $direction = 'decrease'): self
    {
        abort_unless(AdvertisingInvoice::billingAdmin(), 403);
        if (! in_array($direction, ['decrease', 'increase'], true)) {
            throw ValidationException::withMessages(['direction' => __('advertising::refunds.invalid_direction')]);
        }
        $kind = $direction === 'increase' ? 'supplement' : 'correction';
        self::validateAmount($amount);
        $reason = self::validateReason($reason);
        if (! Str::isUuid($operationKey)) {
            throw ValidationException::withMessages(['operation_key' => __('advertising::refunds.invalid_key')]);
        }

        try {
            return DB::transaction(function () use ($payment, $amount, $reason, $operationKey, $kind): self {
                $invoice = AdvertisingInvoice::query()->whereKey($payment->invoice_id)->lockForUpdate()->firstOrFail()->lockFinancials();
                $source = AdvertisingPayment::query()->whereKey($payment->id)->where('invoice_id', $invoice->id)->lockForUpdate()->firstOrFail();
                $existing = self::withTrashed()->where('operation_key', $operationKey)->lockForUpdate()->first();
                if ($existing) {
                    if ($existing->trashed() || $existing->kind !== $kind || (int) $existing->payment_id !== (int) $source->id || $existing->amount !== (int) $amount || ! hash_equals($existing->reason, $reason)) {
                        throw ValidationException::withMessages(['operation_key' => __('advertising::refunds.replay_conflict')]);
                    }

                    return $existing;
                }
                if ($source->online_attempt_id !== null || filled($source->gateway)) {
                    throw ValidationException::withMessages(['amount' => __('advertising::refunds.gateway_correction_forbidden')]);
                }
                if (OnlinePaymentAttempt::unresolved($invoice->id)->exists()
                    || $kind === 'correction' && (int) $amount > self::availableForPayment($source)
                    || $kind === 'supplement' && (! $invoice->collectionOpen() || (int) $amount > $invoice->balance())) {
                    throw ValidationException::withMessages(['amount' => __('advertising::refunds.exceeds_available')]);
                }

                return self::write(['invoice_id' => $invoice->id, 'payment_id' => $source->id, 'recorded_by' => auth()->id(), 'kind' => $kind, 'amount' => (int) $amount, 'reference' => 'CORR-'.$operationKey, 'operation_key' => $operationKey, 'reason' => $reason]);
            }, 3);
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages(['operation_key' => __('advertising::refunds.replay_conflict')]);
        }
    }

    public static function completeRefund(AdvertisingRefundRequest $request, string $reference): self
    {
        abort_unless(AdvertisingInvoice::billingAdmin(), 403);
        if (DB::transactionLevel() < 1) {
            abort(403);
        }
        $locked = AdvertisingRefundRequest::query()->whereKey($request->id)->lockForUpdate()->firstOrFail();
        abort_unless($locked->state === 'completed' && $locked->completed_at && $locked->completed_by, 403);
        $existing = self::withTrashed()->where('refund_request_id', $locked->id)->lockForUpdate()->first();
        if ($existing) {
            if ($existing->trashed() || $existing->reference !== $reference || $existing->amount !== $locked->amount) {
                throw ValidationException::withMessages(['reference' => __('advertising::refunds.replay_conflict')]);
            }

            return $existing;
        }
        $payment = AdvertisingPayment::query()->whereKey($locked->payment_id)->where('invoice_id', $locked->invoice_id)->lockForUpdate()->firstOrFail();
        if ($locked->amount > self::availableForPayment($payment, $locked->id)) {
            throw ValidationException::withMessages(['amount' => __('advertising::refunds.exceeds_available')]);
        }

        return self::write(['invoice_id' => $locked->invoice_id, 'payment_id' => $payment->id, 'refund_request_id' => $locked->id, 'recorded_by' => auth()->id(), 'kind' => 'refund', 'amount' => $locked->amount, 'reference' => $reference, 'operation_key' => $locked->form_key, 'reason' => $locked->reason]);
    }

    public static function availableForPayment(AdvertisingPayment $payment, ?int $excludingRequest = null): int
    {
        $query = self::query()->where('payment_id', $payment->id);
        $entries = DB::transactionLevel() > 0 ? $query->lockForUpdate()->get(['id', 'kind', 'amount']) : $query->get(['id', 'kind', 'amount']);
        $adjusted = (int) $entries->whereIn('kind', ['correction', 'refund'])->sum('amount') - (int) $entries->where('kind', 'supplement')->sum('amount');
        $reservedQuery = AdvertisingRefundRequest::query()->where('payment_id', $payment->id)->whereIn('state', ['requested', 'approved'])
            ->when($excludingRequest, fn ($query) => $query->whereKeyNot($excludingRequest));
        $reserved = DB::transactionLevel() > 0 ? (int) $reservedQuery->lockForUpdate()->get(['id', 'amount'])->sum('amount') : (int) $reservedQuery->sum('amount');

        return max(0, $payment->amount - $adjusted - $reserved);
    }

    public static function validateAmount(string $amount): void
    {
        if (! preg_match('/\A[1-9][0-9]{0,12}\z/', $amount) || (int) $amount > 1000000000000) {
            throw ValidationException::withMessages(['amount' => __('advertising::billing.invalid_amount')]);
        }
    }

    public static function validateReason(string $reason): string
    {
        $reason = trim($reason);
        if ($reason === '' || mb_strlen($reason) > 500) {
            throw ValidationException::withMessages(['reason' => __('advertising::refunds.reason_required')]);
        }

        return $reason;
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logOnly(['invoice_id', 'payment_id', 'refund_request_id', 'recorded_by', 'kind', 'amount', 'reference'])->logOnlyDirty()->dontLogEmptyChanges();
    }

    private static function write(array $data): self
    {
        $entry = new self($data);
        $entry->writing = true;
        $entry->save();
        $entry->writing = false;

        return $entry;
    }
}
