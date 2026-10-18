<?php

declare(strict_types=1);

namespace Modules\Favorite\App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Listing\Models\Listing;
use Modules\User\App\Models\User;

final class BuyerAlert extends Model
{
    use SoftDeletes;

    protected $guarded = [];

    protected $casts = ['enabled' => 'boolean', 'lowest_price' => 'decimal:2'];

    public static function configure(User $user, string $kind, int $sourceId, bool $enabled): void
    {
        DB::transaction(function () use ($user, $kind, $sourceId, $enabled): void {
            User::query()->whereKey($user->id)->lockForUpdate()->firstOrFail();
            $source = self::source($user->id, $kind, $sourceId);
            abort_unless($source, 404);
            $alert = self::withTrashed()->firstOrNew(['user_id' => $user->id, 'kind' => $kind, 'source_id' => $sourceId]);
            if ($enabled && ! $alert->enabled && self::where('user_id', $user->id)->where('enabled', true)->count() >= 20) {
                throw ValidationException::withMessages(['enabled' => __('favorite::alerts.limit')]);
            }
            if ($enabled && (! $alert->exists || ! $alert->enabled || $alert->trashed())) {
                $listing = $kind === 'price' ? Listing::buyerAlertListings([$sourceId])->first() : null;
                $alert->cursor = BuyerPublication::cursor();
                $alert->lowest_price = $listing?->price;
                $alert->currency = $listing?->currency;
            }
            $alert->forceFill(['enabled' => $enabled, 'deleted_at' => null])->save();
        });
    }

    public static function source(int $userId, string $kind, int $id): mixed
    {
        return match ($kind) {
            'search' => FavoriteSearch::where('user_id', $userId)->whereKey($id)->first(),
            'seller' => FavoriteSeller::where('user_id', $userId)->where('seller_id', $id)->first(),
            'price' => FavoriteListing::where('user_id', $userId)->where('listing_id', $id)->first(),
            default => null,
        };
    }

    public static function refreshFor(User $user): void
    {
        if ((string) $user->status !== 'active') {
            return;
        }
        self::where('user_id', $user->id)->where('enabled', true)->orderBy('id')->limit(20)->get()->each(function (self $alert): void {
            DB::transaction(function () use ($alert): void {
                $locked = self::whereKey($alert->id)->lockForUpdate()->first();
                if (! $locked?->enabled) {
                    return;
                }
                $source = self::source($locked->user_id, $locked->kind, $locked->source_id);
                if (! $source) {
                    $locked->update(['enabled' => false]);

                    return;
                }
                if ($locked->kind === 'price') {
                    $locked->checkPrice();

                    return;
                }
                $publications = BuyerPublication::after((int) $locked->cursor);
                if ($publications->isEmpty()) {
                    return;
                }
                $filters = $locked->kind === 'search' ? ($source->filters ?? []) : ['seller' => $locked->source_id];
                foreach (Listing::buyerAlertListings($publications->pluck('listing_id')->all(), $filters, $locked->user_id) as $listing) {
                    BuyerAlertReceipt::deliver($locked->user_id, $locked->kind.'|'.$listing->id, __('favorite::alerts.'.$locked->kind.'_title'), $listing->title, $listing->id);
                }
                $locked->update(['cursor' => $publications->last()->id]);
            });
        });
    }

    private function checkPrice(): void
    {
        $listing = Listing::buyerAlertListings([$this->source_id], [], $this->user_id)->first();
        if (! $listing || $listing->price === null) {
            return;
        }
        if ($this->currency !== $listing->currency || $this->lowest_price === null) {
            $this->update(['currency' => $listing->currency, 'lowest_price' => $listing->price]);

            return;
        }
        if (bccomp((string) $listing->price, (string) $this->lowest_price, 2) < 0) {
            BuyerAlertReceipt::deliver($this->user_id, 'price|'.$listing->id.'|'.$listing->currency.'|'.$listing->price, __('favorite::alerts.price_title'), $listing->title.' · '.$listing->panelPriceLabel(), $listing->id);
            $this->update(['lowest_price' => $listing->price]);
        }
    }

    public function subjectLabel(): string
    {
        return match ($this->kind) {
            'search' => FavoriteSearch::where('user_id', $this->user_id)->whereKey($this->source_id)->value('label') ?? __('favorite::alerts.unavailable'),
            'seller' => User::whereKey($this->source_id)->value('name') ?? __('favorite::alerts.unavailable'),
            default => Listing::buyerAlertListings([$this->source_id])->first()?->title ?? __('favorite::alerts.unavailable'),
        };
    }

    public static function disableSource(int $userId, string $kind, int $sourceId): void
    {
        self::where('user_id', $userId)->where('kind', $kind)->where('source_id', $sourceId)->update(['enabled' => false]);
    }

    public static function pageFor(int $userId): LengthAwarePaginator
    {
        return self::where('user_id', $userId)->latest('id')->paginate(20);
    }

    public static function preferencesFor(int $userId): array
    {
        return self::where('user_id', $userId)->get()->keyBy(fn (self $alert) => $alert->kind.':'.$alert->source_id)->map->enabled->all();
    }
}
