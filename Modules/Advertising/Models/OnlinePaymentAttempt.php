<?php

declare(strict_types=1);

namespace Modules\Advertising\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Modules\Advertising\Services\PaymentGatewayCatalog;
use Modules\Advertising\Services\PaymentGatewayClient;
use Modules\User\App\Models\User;

final class OnlinePaymentAttempt extends Model
{
    use SoftDeletes;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $guarded = [];

    protected $dateFormat = 'Y-m-d H:i:s.u';

    protected $hidden = ['merchant', 'authority', 'authority_hash', 'callback_token', 'callback_hash'];

    private bool $transitioning = false;

    protected function casts(): array
    {
        return ['amount' => 'integer', 'sandbox' => 'boolean', 'merchant' => 'encrypted', 'authority' => 'encrypted', 'callback_token' => 'encrypted', 'checked_at' => 'datetime', 'verified_at' => 'datetime'];
    }

    protected static function booted(): void
    {
        self::saving(function (self $attempt): void {
            abort_unless($attempt->transitioning, 403);
        });
        self::deleting(fn () => abort(403));
    }

    public static function unresolved(int $invoiceId): Builder
    {
        return self::query()->where('invoice_id', $invoiceId)->whereIn('status', ['creating', 'pending', 'needs_review']);
    }

    public static function latestForInvoice(int $invoiceId): ?self
    {
        return self::query()->where('invoice_id', $invoiceId)->latest('created_at')->latest('id')->first();
    }

    public static function start(AdvertisingInvoice $invoice, User $user, string $gateway): self
    {
        abort_unless((string) $user->status === 'active' && (int) $invoice->user_id === (int) $user->id, 403);
        $snapshot = app(PaymentGatewayCatalog::class)->snapshot($gateway);
        if (! $snapshot) {
            throw ValidationException::withMessages(['gateway' => __('advertising::gateway.unavailable')]);
        }
        self::callbackBase();
        $created = false;
        $attempt = DB::transaction(function () use ($invoice, $user, $snapshot, &$created): self {
            $locked = AdvertisingInvoice::query()->lockForUpdate()->findOrFail($invoice->id);
            abort_unless((int) $locked->user_id === (int) $user->id, 403);
            $pending = self::unresolved($locked->id)->lockForUpdate()->first();
            if ($pending && $pending->status === 'creating' && $pending->created_at->lt(now()->subMinutes(2))) {
                $pending->status = 'failed';
                $pending->response_code = 'creation_expired';
                $pending->persist();
                $pending = null;
            }
            if ($pending) {
                if ($locked->voided_at || $locked->balance() !== $pending->amount) {
                    throw ValidationException::withMessages(['gateway' => __('advertising::gateway.in_progress')]);
                }
                if ($pending->gateway === $snapshot['gateway'] && $pending->status === 'pending') {
                    return $pending;
                }
                throw ValidationException::withMessages(['gateway' => __('advertising::gateway.in_progress')]);
            }
            if ($locked->voided_at || $locked->currency !== 'IRR' || ! self::validAmount($locked->balance())) {
                throw ValidationException::withMessages(['gateway' => __('advertising::gateway.invalid_amount')]);
            }
            $token = bin2hex(random_bytes(32));
            $new = new self($snapshot + ['id' => (string) Str::uuid(), 'invoice_id' => $locked->id, 'initiated_by' => $user->id, 'amount' => $locked->balance(), 'callback_token' => $token, 'callback_hash' => hash('sha256', $token), 'status' => 'creating']);
            $new->persist();
            $created = true;

            return $new;
        }, 3);
        if (! $created) {
            return $attempt;
        }
        $result = app(PaymentGatewayClient::class)->request($attempt, $attempt->callbackUrl());

        try {
            return DB::transaction(function () use ($attempt, $result): self {
                AdvertisingInvoice::query()->whereKey($attempt->invoice_id)->lockForUpdate()->firstOrFail();
                $locked = self::query()->whereKey($attempt->id)->lockForUpdate()->firstOrFail();
                if ($locked->status !== 'creating') {
                    return $locked;
                }
                $locked->authority = $result['authority'];
                $locked->authority_hash = $result['authority'] ? hash('sha256', $locked->gateway.':'.($locked->sandbox ? 'test' : 'live').':'.$result['authority']) : null;
                $locked->status = $result['authority'] ? 'pending' : 'failed';
                $locked->response_code = $result['code'];
                $locked->persist();

                return $locked;
            }, 3);
        } catch (UniqueConstraintViolationException) {
            return $attempt->holdForReview('authority_conflict', true);
        }
    }

    public function check(): self
    {
        try {
            return DB::transaction(function (): self {
                AdvertisingInvoice::withTrashed()->whereKey($this->invoice_id)->lockForUpdate()->firstOrFail();
                $attempt = self::query()->whereKey($this->id)->lockForUpdate()->firstOrFail();
                if (in_array($attempt->status, ['verified', 'test_passed'], true) || ! $attempt->authority) {
                    return $attempt;
                }
                if ($attempt->checked_at && $attempt->checked_at->gt(now()->subSeconds(5))) {
                    return $attempt;
                }
                $result = app(PaymentGatewayClient::class)->verify($attempt);
                $attempt->checked_at = now();
                $attempt->response_code = $result['code'];
                $attempt->status = $result['state'];
                if ($result['state'] === 'verified') {
                    $attempt->gateway_reference = $result['reference'];
                    $existing = AdvertisingPayment::withTrashed()->where('reference', $attempt->receiptReference())->first();
                    if (! $attempt->sandbox && $existing && ($existing->trashed() || $existing->online_attempt_id !== $attempt->id)) {
                        $attempt->status = 'needs_review';
                        $attempt->response_code = 'reference_conflict';
                    } else {
                        $attempt->status = $attempt->sandbox ? 'test_passed' : 'verified';
                        $attempt->verified_at = now();
                    }
                }
                $attempt->persist();
                if ($attempt->status === 'verified') {
                    AdvertisingPayment::recordGatewayPayment($attempt);
                }

                return $attempt;
            }, 3);
        } catch (UniqueConstraintViolationException) {
            return $this->holdForReview('reference_conflict');
        }
    }

    public function assertOwned(User $user): void
    {
        abort_unless((string) $user->status === 'active', 403);
        abort_unless(AdvertisingInvoice::query()->whereKey($this->invoice_id)->where('user_id', $user->id)->exists(), 404);
    }

    public function matchesCallback(string $token, mixed $authority): bool
    {
        return hash_equals($this->callback_hash, hash('sha256', $token))
            && is_scalar($authority) && filled($this->authority) && hash_equals($this->authority, (string) $authority);
    }

    public function callbackUrl(): string
    {
        return self::callbackBase().route('payments.callback', ['attempt' => $this->id, 'token' => $this->callback_token], absolute: false);
    }

    private static function callbackBase(): string
    {
        $base = rtrim((string) config('app.url'), '/');
        $parts = parse_url($base);
        abort_unless(is_array($parts) && filled($parts['host'] ?? null) && ! isset($parts['user']) && ! isset($parts['pass']) && ! isset($parts['query']) && ! isset($parts['fragment'])
            && (($parts['scheme'] ?? '') === 'https' || app()->environment(['local', 'testing']) && ($parts['scheme'] ?? '') === 'http'), 422);

        return $base;
    }

    public function receiptReference(): string
    {
        return strtoupper($this->gateway).'-'.$this->gateway_reference;
    }

    public static function validAmount(int $amount): bool
    {
        return $amount >= 1000 && $amount <= 4000000000;
    }

    private function holdForReview(string $code, bool $clearAuthority = false): self
    {
        return DB::transaction(function () use ($code, $clearAuthority): self {
            AdvertisingInvoice::withTrashed()->whereKey($this->invoice_id)->lockForUpdate()->firstOrFail();
            $attempt = self::query()->whereKey($this->id)->lockForUpdate()->firstOrFail();
            if (! in_array($attempt->status, ['verified', 'test_passed'], true)) {
                $attempt->status = 'needs_review';
                $attempt->response_code = $code;
                if ($clearAuthority) {
                    $attempt->authority = null;
                    $attempt->authority_hash = null;
                }
                $attempt->persist();
            }

            return $attempt;
        }, 3);
    }

    private function persist(): void
    {
        $this->transitioning = true;
        try {
            $this->save();
            activity('online-payments')->performedOn(AdvertisingInvoice::withTrashed()->findOrFail($this->invoice_id))
                ->withProperties($this->only(['id', 'initiated_by', 'gateway', 'sandbox', 'amount', 'status', 'gateway_reference', 'response_code', 'checked_at', 'verified_at']))
                ->log('online_payment.'.$this->status);
        } finally {
            $this->transitioning = false;
        }
    }
}
