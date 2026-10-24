<?php

declare(strict_types=1);

namespace Modules\Favorite\App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Modules\Favorite\App\Models\Concerns\SharesShortlist;
use Modules\User\App\Models\User;

final class FavoriteCollection extends Model
{
    use SharesShortlist;
    use SoftDeletes;

    protected $guarded = [];

    protected $hidden = ['share_token', 'share_hash', 'share_ids'];

    protected function casts(): array
    {
        return ['user_id' => 'integer', 'share_token' => 'encrypted', 'share_ids' => 'array', 'share_enabled' => 'boolean', 'share_revision' => 'integer', 'share_expires_at' => 'datetime'];
    }

    public static function optionsFor(int $userId): array
    {
        return self::where('user_id', $userId)->orderBy('name')->pluck('name', 'id')->all();
    }

    public static function assertOwned(int $userId, int $id): self
    {
        return self::where('user_id', $userId)->whereKey($id)->firstOrFail();
    }

    public static function saveFor(User $user, string $name, ?int $id = null): self
    {
        return DB::transaction(function () use ($user, $name, $id): self {
            User::whereKey($user->id)->lockForUpdate()->firstOrFail();
            $name = trim($name);
            $signature = hash('sha256', Str::lower($name));
            $collection = $id ? self::assertOwned($user->id, $id) : new self(['user_id' => $user->id]);
            if ($name === '' || Str::length($name) > 60 || self::where('user_id', $user->id)->where('signature', $signature)->when($id, fn ($query) => $query->whereKeyNot($id))->exists()) {
                throw ValidationException::withMessages(['name' => __('favorite::collections.name_invalid')]);
            }
            if (! $id && self::where('user_id', $user->id)->count() >= 50) {
                throw ValidationException::withMessages(['name' => __('favorite::collections.limit')]);
            }
            $collection->forceFill(['name' => $name, 'signature' => $signature])->save();

            return $collection;
        });
    }

    public static function removeFor(User $user, int $id): void
    {
        DB::transaction(function () use ($user, $id): void {
            User::whereKey($user->id)->lockForUpdate()->firstOrFail();
            $collection = self::assertOwned($user->id, $id);
            FavoriteListing::withTrashed()->where('user_id', $user->id)->where('collection_id', $id)->update(['collection_id' => null]);
            $collection->delete();
        });
    }
}
