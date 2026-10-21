<?php

declare(strict_types=1);

namespace Modules\Advertising\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

final class AdvertisingBundle extends Model
{
    use LogsActivity;
    use SoftDeletes;

    protected $fillable = ['name', 'description', 'price', 'items', 'is_active'];

    protected function casts(): array
    {
        return ['price' => 'integer', 'items' => 'array', 'is_active' => 'boolean'];
    }

    protected static function booted(): void
    {
        self::saving(function (self $bundle): void {
            abort_unless(AdvertisingInvoice::billingAdmin(), 403);
            Validator::make(array_replace($bundle->getAttributes(), ['items' => $bundle->items]), [
                'name' => ['required', 'string', 'max:120'], 'description' => ['nullable', 'string', 'max:500'],
                'price' => ['required', 'integer', 'min:1', 'max:1000000000000'],
                'items' => ['required', 'array', 'min:2', 'max:6'],
                'items.*.placement' => ['required', 'string', 'distinct', Rule::in(array_diff(BannerPlacement::KEYS, ['category-top']))],
                'items.*.device' => ['required', Rule::in(['both', 'mobile', 'desktop'])],
                'items.*.duration_days' => ['required', 'integer', 'min:1', 'max:90'],
            ])->validate();
            $bundle->items = array_values(array_map(fn (array $item): array => ['placement' => $item['placement'], 'device' => $item['device'], 'duration_days' => (int) $item['duration_days']], $bundle->items));
            if (trim($bundle->name) === '' || ($bundle->is_active && $bundle->offer() === null)) {
                throw ValidationException::withMessages(['items' => __('advertising::bundles.invalid_items')]);
            }
        });
        self::deleting(fn () => throw ValidationException::withMessages(['bundle' => __('advertising::bundles.no_delete')]));
    }

    private static function activePlacements(): array
    {
        return BannerPlacement::query()->where('is_active', true)->where('key', '!=', 'category-top')->pluck('device', 'key')->all();
    }

    public function offer(?array $placements = null): ?array
    {
        $placements ??= self::activePlacements();
        $items = array_map(fn (array $item): array => ['placement' => $item['placement'], 'device' => $item['device'], 'duration_days' => (int) $item['duration_days']], $this->items);
        foreach ($items as $item) {
            if (! isset($placements[$item['placement']]) || ($placements[$item['placement']] !== 'both' && $placements[$item['placement']] !== $item['device'])) {
                return null;
            }
        }

        return ['name' => $this->name, 'description' => $this->description ?? '', 'price' => $this->price, 'items' => $items];
    }

    public static function version(array $offer): string
    {
        return hash('sha256', json_encode($offer, JSON_THROW_ON_ERROR));
    }

    public static function catalog(): array
    {
        $placements = self::activePlacements();

        return self::query()->where('is_active', true)->latest('id')->limit(100)->get()->map(function (self $bundle) use ($placements): ?array {
            $offer = $bundle->offer($placements);

            return $offer ? ['id' => $bundle->id, 'offer' => $offer, 'version' => self::version($offer)] : null;
        })->filter()->values()->all();
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logOnly($this->fillable)->logOnlyDirty()->dontLogEmptyChanges();
    }
}
