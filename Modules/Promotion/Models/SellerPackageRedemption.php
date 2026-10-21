<?php

declare(strict_types=1);

namespace Modules\Promotion\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Modules\Listing\Models\Listing;

final class SellerPackageRedemption extends Model
{
    use SoftDeletes;

    protected $guarded = ['id'];

    private bool $writing = false;

    protected static function booted(): void
    {
        self::saving(function (self $redemption): void {
            if (! $redemption->writing || $redemption->exists) {
                throw ValidationException::withMessages(['package' => __('promotion::packages.immutable')]);
            }
        });
        self::deleting(fn () => throw ValidationException::withMessages(['package' => __('promotion::packages.immutable')]));
    }

    public static function consume(int $purchaseId, int $listingId, string $requestKey): self
    {
        SellerPackagePurchase::assertActive();
        Validator::make(['request_key' => $requestKey], ['request_key' => ['required', 'uuid']])->validate();
        $requestKey = strtolower($requestKey);

        return DB::transaction(function () use ($purchaseId, $listingId, $requestKey): self {
            $purchase = SellerPackagePurchase::query()->where('user_id', auth()->id())->lockForUpdate()->findOrFail($purchaseId);
            $existing = self::withTrashed()->where('purchase_id', $purchaseId)->where('request_key', $requestKey)->lockForUpdate()->first();
            if ($existing) {
                if ($existing->trashed() || (int) $existing->listing_id !== $listingId) {
                    throw ValidationException::withMessages(['package' => __('promotion::packages.replay_conflict')]);
                }

                return $existing;
            }
            if ($purchase->displayState() !== 'active') {
                throw ValidationException::withMessages(['package' => __('promotion::packages.invalid_state')]);
            }
            $listing = Listing::query()->where('user_id', auth()->id())->lockForUpdate()->findOrFail($listingId);
            if ($listing->statusValue() !== 'active' || ($listing->expires_at && $listing->expires_at->lessThanOrEqualTo(now()))) {
                throw ValidationException::withMessages(['listing_id' => __('promotion::packages.invalid_listing')]);
            }
            if (PromotionOrder::query()->forListing($listingId)->active()->lockForUpdate()->first(['id'])) {
                throw ValidationException::withMessages(['listing_id' => __('promotion::packages.already_promoted')]);
            }
            $snapshot = $purchase->snapshot;
            $plan = new PromotionPlan(['name' => $snapshot['plan_name'], 'price' => '0.00', 'currency' => 'IRR', 'duration_days' => $snapshot['promotion_days']]);
            $plan->id = $snapshot['plan_id'];
            $order = PromotionOrder::open($plan, (int) auth()->id(), $listingId);
            $purchase->spendCredit();
            $redemption = new self(['purchase_id' => $purchaseId, 'listing_id' => $listingId, 'promotion_order_id' => $order->id, 'request_key' => $requestKey]);
            $redemption->writing = true;
            $redemption->save();
            $redemption->writing = false;

            return $redemption;
        });
    }

    public static function listingOptions(): array
    {
        return Listing::query()->where('user_id', auth()->id())->active()->where(fn ($query) => $query->whereNull('expires_at')->orWhere('expires_at', '>', now()))->latest('id')->limit(100)->pluck('title', 'id')->all();
    }
}
