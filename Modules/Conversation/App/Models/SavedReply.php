<?php

declare(strict_types=1);

namespace Modules\Conversation\App\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\User\App\Models\User;

final class SavedReply extends Model
{
    use SoftDeletes;

    public const LIMIT = 50;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['body' => 'encrypted', 'version' => 'integer', 'position' => 'integer'];
    }

    public static function forOwner(User $user): Collection
    {
        return self::where('user_id', $user->getKey())->orderBy('position')->orderBy('id')->limit(self::LIMIT)->get();
    }

    public static function storeFor(User $user, array $data): self
    {
        abort_unless((string) $user->status === 'active', 403);

        return DB::transaction(function () use ($user, $data): self {
            User::whereKey($user->getKey())->lockForUpdate()->firstOrFail();
            if (self::where('user_id', $user->getKey())->count() >= self::LIMIT) {
                throw ValidationException::withMessages(['title' => __('conversation::replies.capacity', ['count' => self::LIMIT])]);
            }

            return self::create(['version' => 1, 'user_id' => $user->getKey(), 'title' => $data['title'], 'body' => $data['body'], 'position' => $data['position'] ?? 0]);
        });
    }

    public static function changeFor(User $user, int $id, int $version, ?array $data): void
    {
        abort_unless((string) $user->status === 'active', 403);
        DB::transaction(function () use ($user, $id, $version, $data): void {
            $reply = self::whereKey($id)->where('user_id', $user->getKey())->lockForUpdate()->firstOrFail();
            if ($reply->version !== $version) {
                throw ValidationException::withMessages(['reply' => __('conversation::replies.conflict')]);
            }
            if ($data === null) {
                $reply->forceFill(['body' => '', 'title' => '', 'version' => $version + 1])->save();
                $reply->delete();

                return;
            }
            $reply->forceFill(['title' => $data['title'], 'body' => $data['body'], 'position' => $data['position'] ?? 0, 'version' => $version + 1])->save();
        });
    }
}
