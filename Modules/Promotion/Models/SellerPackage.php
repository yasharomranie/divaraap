<?php

declare(strict_types=1);

namespace Modules\Promotion\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Modules\Advertising\Models\AdvertisingInvoice;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

final class SellerPackage extends Model
{
    use LogsActivity;
    use SoftDeletes;

    protected $fillable = ['name', 'description', 'promotion_plan_id', 'price', 'credits', 'validity_days', 'is_active'];

    protected function casts(): array
    {
        return ['price' => 'integer', 'credits' => 'integer', 'validity_days' => 'integer', 'is_active' => 'boolean'];
    }

    protected static function booted(): void
    {
        self::saving(function (self $package): void {
            abort_unless(AdvertisingInvoice::billingAdmin(), 403);
            Validator::make($package->getAttributes(), ['name' => ['required', 'string', 'max:80'], 'description' => ['nullable', 'string', 'max:500'], 'price' => ['required', 'integer', 'min:1', 'max:1000000000000'], 'credits' => ['required', 'integer', 'min:1', 'max:1000'], 'validity_days' => ['required', 'integer', 'min:1', 'max:365'], 'promotion_plan_id' => ['required', 'integer', Rule::exists('promotion_plans', 'id')]])->validate();
            if (trim($package->name) === '' || ($package->is_active && $package->offer() === null)) {
                throw ValidationException::withMessages(['promotion_plan_id' => __('promotion::packages.invalid_plan')]);
            }
        });
        self::deleting(fn () => throw ValidationException::withMessages(['package' => __('promotion::packages.no_delete')]));
    }

    public function offer(): ?array
    {
        return $this->offerForPlan(PromotionPlan::query()->active()->find($this->promotion_plan_id));
    }

    private function offerForPlan(?PromotionPlan $plan): ?array
    {
        if (! $plan || $plan->duration_days < 1 || $plan->duration_days > 90 || trim($plan->name) === '') {
            return null;
        }

        return ['name' => $this->name, 'description' => $this->description ?? '', 'price' => $this->price, 'credits' => $this->credits, 'validity_days' => $this->validity_days, 'plan_id' => (int) $plan->id, 'plan_name' => $plan->name, 'promotion_days' => $plan->duration_days];
    }

    public static function catalog(): array
    {
        $packages = self::query()->where('is_active', true)->latest('id')->limit(100)->get();
        $plans = PromotionPlan::query()->active()->whereIn('id', $packages->pluck('promotion_plan_id'))->get()->keyBy('id');

        return $packages->map(function (self $package) use ($plans): ?array {
            $offer = $package->offerForPlan($plans->get($package->promotion_plan_id));

            return $offer ? ['id' => $package->id, 'offer' => $offer, 'version' => self::version($offer)] : null;
        })->filter()->values()->all();
    }

    public static function planOptions(): array
    {
        return PromotionPlan::query()->active()->whereBetween('duration_days', [1, 90])->orderBy('name')->pluck('name', 'id')->all();
    }

    public static function version(array $offer): string
    {
        return hash('sha256', json_encode($offer, JSON_THROW_ON_ERROR));
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logOnly($this->fillable)->logOnlyDirty()->dontLogEmptyChanges();
    }
}
