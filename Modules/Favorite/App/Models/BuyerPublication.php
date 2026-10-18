<?php

declare(strict_types=1);

namespace Modules\Favorite\App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Collection;
use Modules\Listing\Models\Listing;

final class BuyerPublication extends Model
{
    use SoftDeletes;

    public static function record(Listing $listing): void
    {
        if ($listing->statusValue() === 'active' && ($listing->wasRecentlyCreated || $listing->wasChanged('status'))) {
            self::query()->insertOrIgnore(['listing_id' => $listing->id, 'created_at' => now(), 'updated_at' => now()]);
        }
    }

    public static function cursor(): int
    {
        return (int) self::query()->max('id');
    }

    public static function after(int $cursor): Collection
    {
        return self::query()->where('id', '>', $cursor)->orderBy('id')->limit(50)->get();
    }
}
