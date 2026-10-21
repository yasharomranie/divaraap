<?php

declare(strict_types=1);

namespace Modules\Promotion\Models;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Advertising\Models\AdvertisingInvoice;
use Modules\User\App\Models\User;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

final class SellerPackagePurchase extends Model
{
    use LogsActivity;
    use SoftDeletes;

    protected $guarded = ['id'];

    private bool $writing = false;

    protected function casts(): array
    {
        return ['snapshot' => 'array', 'used_credits' => 'integer', 'starts_at' => 'datetime', 'ends_at' => 'datetime'];
    }

    protected static function booted(): void
    {
        self::saving(function (self $purchase): void {
            if (! $purchase->writing) {
                throw ValidationException::withMessages(['package' => __('promotion::packages.immutable')]);
            }
        });
        self::deleting(fn () => throw ValidationException::withMessages(['package' => __('promotion::packages.immutable')]));
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

    public static function requestPackage(int $packageId, string $version): self
    {
        self::assertActive();

        return DB::transaction(function () use ($packageId, $version): self {
            User::query()->lockForUpdate()->findOrFail(auth()->id());
            $package = SellerPackage::query()->where('is_active', true)->findOrFail($packageId);
            $offer = $package->offer();
            if (! $offer || ! hash_equals(SellerPackage::version($offer), $version)) {
                throw ValidationException::withMessages(['package' => __('promotion::packages.changed')]);
            }
            $existing = self::query()->where('user_id', auth()->id())->where(function ($query): void {
                $query->whereIn('state', ['requested', 'invoiced'])->orWhere(fn ($active) => $active->where('state', 'active')->where('ends_at', '>', now()));
            })->lockForUpdate()->get();
            foreach ($existing as $purchase) {
                if ($purchase->state === 'requested' && (int) $purchase->package_id === $packageId && $purchase->snapshot == $offer) {
                    return $purchase;
                }
                if ($purchase->state !== 'active' || $purchase->remaining() > 0) {
                    throw ValidationException::withMessages(['package' => __('promotion::packages.already_open')]);
                }
            }
            $purchase = new self(['user_id' => auth()->id(), 'package_id' => $packageId, 'snapshot' => $offer, 'state' => 'requested', 'used_credits' => 0]);
            $purchase->persist();

            return $purchase;
        });
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(AdvertisingInvoice::class, 'invoice_id');
    }

    public function redemptions(): HasMany
    {
        return $this->hasMany(SellerPackageRedemption::class, 'purchase_id')->latest('id');
    }

    public static function forCustomer(int $userId): LengthAwarePaginator
    {
        return self::query()->where('user_id', $userId)->with('invoice')->latest('id')->paginate(10);
    }

    public static function owned(int $id): self
    {
        self::assertActive();

        return self::query()->where('user_id', auth()->id())->with(['invoice', 'redemptions'])->findOrFail($id);
    }

    public function remaining(): int
    {
        return max(0, (int) $this->snapshot['credits'] - $this->used_credits);
    }

    public function displayState(): string
    {
        return $this->state !== 'active' ? $this->state : ($this->ends_at->lessThanOrEqualTo(now()) ? 'expired' : ($this->remaining() === 0 ? 'spent' : 'active'));
    }

    public function review(string $action, ?string $reason = null): void
    {
        abort_unless(AdvertisingInvoice::billingAdmin(), 403);
        DB::transaction(function () use ($action, $reason): void {
            $purchase = self::query()->lockForUpdate()->findOrFail($this->id);
            if ($action === 'invoice' && $purchase->state === 'requested') {
                $invoice = AdvertisingInvoice::create(['user_id' => $purchase->user_id, 'description' => $purchase->snapshot['name'].' #'.$purchase->id, 'amount' => $purchase->snapshot['price']]);
                $purchase->forceFill(['state' => 'invoiced', 'invoice_id' => $invoice->id]);
            } elseif ($action === 'activate' && $purchase->state === 'invoiced') {
                $invoice = AdvertisingInvoice::query()->lockForUpdate()->findOrFail($purchase->invoice_id);
                if ($invoice->voided_at || $invoice->billingState() !== 'paid') {
                    throw ValidationException::withMessages(['package' => __('promotion::packages.unpaid')]);
                }
                $purchase->forceFill(['state' => 'active', 'starts_at' => now(), 'ends_at' => now()->addDays($purchase->snapshot['validity_days'])]);
            } elseif ($action === 'cancel' && in_array($purchase->state, ['requested', 'invoiced'], true)) {
                if (trim($reason ?? '') === '' || mb_strlen($reason) > 500) {
                    throw ValidationException::withMessages(['reason' => __('advertising::billing.reason_required')]);
                }
                if ($purchase->invoice_id) {
                    $purchase->invoice->voidInvoice($reason);
                }
                $purchase->forceFill(['state' => 'cancelled', 'cancel_reason' => trim($reason)]);
            } else {
                throw ValidationException::withMessages(['package' => __('promotion::packages.invalid_state')]);
            }
            $purchase->reviewed_by = auth()->id();
            $purchase->persist();
        });
        $this->refresh();
    }

    public function spendCredit(): void
    {
        if (DB::transactionLevel() < 1 || (int) $this->user_id !== (int) auth()->id() || $this->displayState() !== 'active') {
            throw ValidationException::withMessages(['package' => __('promotion::packages.invalid_state')]);
        }
        $locked = self::query()->where('user_id', auth()->id())->lockForUpdate()->findOrFail($this->id);
        if ($locked->displayState() !== 'active') {
            throw ValidationException::withMessages(['package' => __('promotion::packages.invalid_state')]);
        }
        $locked->used_credits++;
        $locked->persist();
        $this->refresh();
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logOnly(['user_id', 'package_id', 'snapshot', 'state', 'invoice_id', 'used_credits', 'starts_at', 'ends_at', 'reviewed_by', 'cancel_reason'])->logOnlyDirty()->dontLogEmptyChanges();
    }
}
