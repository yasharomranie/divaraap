<?php

declare(strict_types=1);

namespace Modules\Favorite\App\Models;

use Illuminate\Database\Eloquent\Relations\Pivot;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;
use Modules\User\App\Models\User;

class FavoriteListing extends Pivot
{
    use SoftDeletes;

    protected $table = 'favorite_listings';

    public $incrementing = true;

    public static function rememberFor(User $user, int $listingId): void
    {
        DB::transaction(function () use ($user, $listingId): void {
            User::whereKey($user->id)->lockForUpdate()->firstOrFail();
            $favorite = self::withTrashed()->where('user_id', $user->id)->where('listing_id', $listingId)->first();
            if ($favorite && ! $favorite->trashed()) {
                return;
            }
            $favorite ??= new self;
            $favorite->forceFill(['user_id' => $user->id, 'listing_id' => $listingId, 'collection_id' => null, 'deleted_at' => null, 'created_at' => now()])->save();
        });
    }

    public static function moveFor(User $user, int $listingId, ?int $collectionId): void
    {
        DB::transaction(function () use ($user, $listingId, $collectionId): void {
            User::whereKey($user->id)->lockForUpdate()->firstOrFail();
            if ($collectionId !== null) {
                FavoriteCollection::assertOwned($user->id, $collectionId);
            }
            $favorite = self::where('user_id', $user->id)->where('listing_id', $listingId)->lockForUpdate()->firstOrFail();
            $favorite->forceFill(['collection_id' => $collectionId])->save();
        });
    }

    public static function countsForListings(array $listingIds): array
    {
        $unique = array_values(array_unique(array_map(static fn (int $id): int => $id, $listingIds)));

        if ($unique === []) {
            return [];
        }

        return static::query()
            ->whereIn('listing_id', $unique)
            ->selectRaw('listing_id, COUNT(*) as aggregate')
            ->groupBy('listing_id')
            ->pluck('aggregate', 'listing_id')
            ->mapWithKeys(static fn (mixed $count, mixed $listingId): array => [(int) $listingId => (int) $count])
            ->all();
    }

    public static function countForUser(int $userId): int
    {
        return static::query()->where('user_id', $userId)->count();
    }
}
