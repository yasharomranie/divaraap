<?php

declare(strict_types=1);

namespace Modules\Advertising\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

final class AdvertisingPayment extends Model
{
    use LogsActivity;
    use SoftDeletes;

    protected $table = 'advertising_payments';

    protected $guarded = ['id'];

    private bool $recording = false;

    protected function casts(): array
    {
        return ['amount' => 'integer'];
    }

    protected static function booted(): void
    {
        self::saving(function (self $payment): void {
            if (! $payment->recording || $payment->exists) {
                throw ValidationException::withMessages(['payment' => __('advertising::billing.immutable')]);
            }
        });
        self::deleting(fn () => throw ValidationException::withMessages(['payment' => __('advertising::billing.immutable')]));
    }

    public static function recordReceived(AdvertisingInvoice $invoice, string $amount, string $reference, ?string $note = null): self
    {
        abort_unless(AdvertisingInvoice::billingAdmin(), 403);
        $reference = strtoupper(trim($reference));
        if (str_starts_with($reference, 'ZARINPAL-') || str_starts_with($reference, 'ZIBAL-')) {
            throw ValidationException::withMessages(['reference' => __('advertising::gateway.reserved_reference')]);
        }
        if (! preg_match('/^[1-9][0-9]{0,12}$/D', $amount) || (int) $amount > 1000000000000) {
            throw ValidationException::withMessages(['amount' => __('advertising::billing.invalid_amount')]);
        }
        if (! preg_match('/^[A-Z0-9][A-Z0-9_-]{3,79}$/D', $reference)) {
            throw ValidationException::withMessages(['reference' => __('advertising::billing.invalid_reference')]);
        }
        if (mb_strlen($note ?? '') > 500) {
            throw ValidationException::withMessages(['note' => __('advertising::billing.invalid_description')]);
        }
        try {
            return DB::transaction(function () use ($invoice, $amount, $reference, $note): self {
                $locked = AdvertisingInvoice::query()->lockForUpdate()->findOrFail($invoice->id);
                $existing = self::withTrashed()->where('reference', $reference)->first();
                if ($existing) {
                    return self::matchingReceipt($existing, $locked, (int) $amount);
                }
                if (OnlinePaymentAttempt::unresolved($locked->id)->exists()) {
                    throw ValidationException::withMessages(['amount' => __('advertising::gateway.in_progress')]);
                }
                if ($locked->voided_at || (int) $amount > $locked->balance()) {
                    throw ValidationException::withMessages(['amount' => __('advertising::billing.exceeds_balance')]);
                }
                $payment = new self(['invoice_id' => $locked->id, 'recorded_by' => auth()->id(), 'amount' => (int) $amount, 'reference' => $reference, 'note' => trim($note ?? '') ?: null]);
                $payment->recording = true;
                $payment->save();

                return $payment;
            });
        } catch (QueryException $exception) {
            $existing = self::withTrashed()->where('reference', $reference)->first();
            if (! $existing) {
                throw $exception;
            }

            return self::matchingReceipt($existing, $invoice, (int) $amount);
        }
    }

    public static function recordGatewayPayment(OnlinePaymentAttempt $attempt): self
    {
        return DB::transaction(function () use ($attempt): self {
            $invoice = AdvertisingInvoice::withTrashed()->whereKey($attempt->invoice_id)->lockForUpdate()->firstOrFail();
            $verified = OnlinePaymentAttempt::query()->whereKey($attempt->id)->lockForUpdate()->firstOrFail();
            abort_unless($verified->status === 'verified' && $verified->verified_at && ! $verified->sandbox, 403);
            $existing = self::withTrashed()->where('online_attempt_id', $verified->id)->first();
            if ($existing) {
                return self::matchingReceipt($existing, $invoice, $verified->amount);
            }
            $payment = new self(['invoice_id' => $invoice->id, 'recorded_by' => $verified->initiated_by, 'amount' => $verified->amount, 'reference' => $verified->receiptReference(), 'online_attempt_id' => $verified->id, 'gateway' => $verified->gateway]);
            $payment->recording = true;
            $payment->save();

            return $payment;
        }, 3);
    }

    private static function matchingReceipt(self $payment, AdvertisingInvoice $invoice, int $amount): self
    {
        if ($payment->trashed() || (int) $payment->invoice_id !== (int) $invoice->id || $payment->amount !== $amount) {
            throw ValidationException::withMessages(['reference' => __('advertising::billing.duplicate_reference')]);
        }

        return $payment;
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logOnly(['invoice_id', 'recorded_by', 'amount', 'reference', 'note', 'gateway', 'online_attempt_id'])->logOnlyDirty()->dontLogEmptyChanges();
    }
}
