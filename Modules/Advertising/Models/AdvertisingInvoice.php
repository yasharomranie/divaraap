<?php

declare(strict_types=1);

namespace Modules\Advertising\Models;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Modules\User\App\Models\User;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

final class AdvertisingInvoice extends Model
{
    use LogsActivity;
    use SoftDeletes;

    protected $table = 'advertising_invoices';

    protected $fillable = ['user_id', 'campaign_id', 'description', 'amount'];

    private bool $voiding = false;

    protected function casts(): array
    {
        return ['customer' => 'array', 'amount' => 'integer', 'voided_at' => 'datetime'];
    }

    public static function billingAdmin(): bool
    {
        return auth()->check() && (string) auth()->user()->status === 'active' && auth()->user()->hasRole('admin');
    }

    public static function customerOptions(string $search = ''): array
    {
        return User::query()->where('status', 'active')
            ->when(trim($search) !== '', fn ($query) => $query->where('email', 'like', '%'.mb_substr($search, 0, 120).'%'))
            ->orderBy('id')->limit(50)->get(['id', 'name', 'email'])->mapWithKeys(fn (User $user): array => [$user->id => $user->name.' · '.$user->email])->all();
    }

    public static function customerLabel(int $id): ?string
    {
        $user = User::query()->where('status', 'active')->find($id);

        return $user ? $user->name.' · '.$user->email : null;
    }

    protected static function booted(): void
    {
        self::saving(function (self $invoice): void {
            abort_unless(self::billingAdmin(), 403);
            if ($invoice->exists) {
                if (! $invoice->voiding || $invoice->isDirty(['number', 'user_id', 'issued_by', 'campaign_id', 'customer', 'description', 'amount', 'currency', 'created_at', 'deleted_at'])) {
                    throw ValidationException::withMessages(['amount' => __('advertising::billing.immutable')]);
                }

                return;
            }
            $user = User::query()->where('status', 'active')->find($invoice->user_id);
            if (! $user) {
                throw ValidationException::withMessages(['user_id' => __('advertising::billing.invalid_customer')]);
            }
            if (! preg_match('/^[1-9][0-9]{0,12}$/D', (string) ($invoice->getAttributes()['amount'] ?? '')) || (int) $invoice->amount > 1000000000000) {
                throw ValidationException::withMessages(['amount' => __('advertising::billing.invalid_amount')]);
            }
            if (trim((string) $invoice->description) === '' || mb_strlen($invoice->description) > 500) {
                throw ValidationException::withMessages(['description' => __('advertising::billing.invalid_description')]);
            }
            if ($invoice->campaign_id !== null && ! Campaign::query()->whereKey($invoice->campaign_id)->exists()) {
                throw ValidationException::withMessages(['campaign_id' => __('advertising::billing.invalid_campaign')]);
            }
            $invoice->number = 'AD-'.Str::ulid();
            $invoice->issued_by = auth()->id();
            $invoice->customer = ['name' => $user->name, 'email' => $user->email];
            $invoice->currency = 'IRR';
            $invoice->voided_at = null;
            $invoice->void_reason = null;
            $invoice->voided_by = null;
        });
        self::deleting(fn () => throw ValidationException::withMessages(['invoice' => __('advertising::billing.immutable')]));
    }

    public static function invoicesForCustomer(int $userId): LengthAwarePaginator
    {
        return self::financialQuery()->where('user_id', $userId)->latest('id')->paginate(15);
    }

    public static function accessibleInvoice(string $id, int $userId): self
    {
        return self::financialQuery()->when(! self::billingAdmin(), fn ($query) => $query->where('user_id', $userId))
            ->with(['payments' => fn ($query) => $query->oldest('id'), 'adjustments' => fn ($query) => $query->oldest('id'), 'refundRequests' => fn ($query) => $query->latest('id')])->findOrFail($id);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(AdvertisingPayment::class, 'invoice_id');
    }

    public function adjustments(): HasMany
    {
        return $this->hasMany(AdvertisingPaymentAdjustment::class, 'invoice_id');
    }

    public function refundRequests(): HasMany
    {
        return $this->hasMany(AdvertisingRefundRequest::class, 'invoice_id');
    }

    public static function financialQuery(): Builder
    {
        return self::query()->withSum('payments', 'amount')
            ->withSum(['adjustments as corrected_total' => fn ($query) => $query->where('kind', 'correction')], 'amount')
            ->withSum(['adjustments as supplemented_total' => fn ($query) => $query->where('kind', 'supplement')], 'amount')
            ->withSum(['adjustments as refunded_total' => fn ($query) => $query->where('kind', 'refund')], 'amount')
            ->withSum(['refundRequests as reserved_refund_total' => fn ($query) => $query->whereIn('state', ['requested', 'approved'])], 'amount');
    }

    public function lockFinancials(): self
    {
        if (DB::transactionLevel() < 1) {
            throw new \LogicException('Financial snapshots require an active transaction.');
        }
        $locked = self::query()->whereKey($this->id)->lockForUpdate()->firstOrFail();
        $locked->setRelation('payments', $locked->payments()->lockForUpdate()->get());
        $locked->setRelation('adjustments', $locked->adjustments()->lockForUpdate()->get());
        $locked->setRelation('refundRequests', $locked->refundRequests()->lockForUpdate()->get());

        return $locked;
    }

    public function grossReceivedAmount(): int
    {
        return array_key_exists('payments_sum_amount', $this->getAttributes()) ? (int) $this->getAttribute('payments_sum_amount')
            : (int) ($this->relationLoaded('payments') ? $this->payments->sum('amount') : $this->payments()->sum('amount'));
    }

    public function correctedAmount(): int
    {
        return $this->adjustmentTotal('correction', 'corrected_total');
    }

    public function supplementedAmount(): int
    {
        return $this->adjustmentTotal('supplement', 'supplemented_total');
    }

    public function refundedAmount(): int
    {
        return $this->adjustmentTotal('refund', 'refunded_total');
    }

    public function reservedRefundAmount(): int
    {
        return array_key_exists('reserved_refund_total', $this->getAttributes()) ? (int) $this->getAttribute('reserved_refund_total')
            : ($this->relationLoaded('refundRequests') ? (int) $this->refundRequests->whereIn('state', ['requested', 'approved'])->sum('amount') : (int) $this->refundRequests()->whereIn('state', ['requested', 'approved'])->sum('amount'));
    }

    public function paidAmount(): int
    {
        return max(0, $this->grossReceivedAmount() - $this->correctedAmount() + $this->supplementedAmount());
    }

    public function netReceivedAmount(): int
    {
        return max(0, $this->paidAmount() - $this->refundedAmount());
    }

    public function collectionOpen(): bool
    {
        return ! $this->voided_at && $this->refundedAmount() === 0 && $this->reservedRefundAmount() === 0;
    }

    public function balance(): int
    {
        return $this->voided_at || $this->refundedAmount() > 0 ? 0 : max(0, $this->amount - $this->paidAmount());
    }

    public function unallocatedAmount(): int
    {
        return $this->voided_at ? $this->netReceivedAmount() : max(0, $this->netReceivedAmount() - $this->amount);
    }

    public function billingState(): string
    {
        if ($this->voided_at) {
            return 'void';
        }
        $net = $this->netReceivedAmount();
        $reserved = $this->reservedRefundAmount();
        if ($reserved > 0 && $net - $reserved < $this->amount) {
            return 'refund_pending';
        }
        if ($net >= $this->amount) {
            return 'paid';
        }
        if ($this->refundedAmount() > 0) {
            return $net > 0 ? 'partially_refunded' : 'refunded';
        }

        return $this->paidAmount() > 0 ? 'partial' : 'unpaid';
    }

    private function adjustmentTotal(string $kind, string $alias): int
    {
        return array_key_exists($alias, $this->getAttributes()) ? (int) $this->getAttribute($alias)
            : ($this->relationLoaded('adjustments') ? (int) $this->adjustments->where('kind', $kind)->sum('amount') : (int) $this->adjustments()->where('kind', $kind)->sum('amount'));
    }

    public function voidInvoice(string $reason): void
    {
        abort_unless(self::billingAdmin(), 403);
        if (trim($reason) === '' || mb_strlen($reason) > 500) {
            throw ValidationException::withMessages(['reason' => __('advertising::billing.reason_required')]);
        }
        DB::transaction(function () use ($reason): void {
            $invoice = self::query()->lockForUpdate()->findOrFail($this->id)->lockFinancials();
            if ($invoice->voided_at || $invoice->paidAmount() > 0 || $invoice->reservedRefundAmount() > 0 || OnlinePaymentAttempt::unresolved($invoice->id)->exists()) {
                throw ValidationException::withMessages(['reason' => __('advertising::billing.cannot_void')]);
            }
            $invoice->voiding = true;
            $invoice->forceFill(['voided_at' => now(), 'voided_by' => auth()->id(), 'void_reason' => trim($reason)])->save();
        });
        $this->refresh();
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logOnly(['number', 'user_id', 'amount', 'currency', 'campaign_id', 'voided_at', 'voided_by', 'void_reason'])->logOnlyDirty()->dontLogEmptyChanges();
    }
}
