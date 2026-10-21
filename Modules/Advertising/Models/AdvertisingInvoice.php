<?php

declare(strict_types=1);

namespace Modules\Advertising\Models;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
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
        return self::query()->where('user_id', $userId)->withSum('payments', 'amount')->latest('id')->paginate(15);
    }

    public static function accessibleInvoice(string $id, int $userId): self
    {
        return self::query()->when(! self::billingAdmin(), fn ($query) => $query->where('user_id', $userId))
            ->with(['payments' => fn ($query) => $query->oldest('id')])->findOrFail($id);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(AdvertisingPayment::class, 'invoice_id');
    }

    public function paidAmount(): int
    {
        return (int) ($this->payments_sum_amount ?? ($this->relationLoaded('payments') ? $this->payments->sum('amount') : $this->payments()->sum('amount')));
    }

    public function balance(): int
    {
        return $this->voided_at ? 0 : max(0, $this->amount - $this->paidAmount());
    }

    public function billingState(): string
    {
        return $this->voided_at ? 'void' : ($this->paidAmount() >= $this->amount ? 'paid' : ($this->paidAmount() > 0 ? 'partial' : 'unpaid'));
    }

    public function voidInvoice(string $reason): void
    {
        abort_unless(self::billingAdmin(), 403);
        if (trim($reason) === '' || mb_strlen($reason) > 500) {
            throw ValidationException::withMessages(['reason' => __('advertising::billing.reason_required')]);
        }
        DB::transaction(function () use ($reason): void {
            $invoice = self::query()->lockForUpdate()->findOrFail($this->id);
            if ($invoice->voided_at || $invoice->paidAmount() > 0) {
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
