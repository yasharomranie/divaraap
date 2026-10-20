<?php

declare(strict_types=1);

namespace Modules\Conversation\App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\User\App\Models\User;

final class ConversationBlock extends Model
{
    use SoftDeletes;

    protected $guarded = ['id'];

    public static function between(int $left, int $right): bool
    {
        return self::query()->where(fn ($query) => $query->where('blocker_id', $left)->where('blocked_id', $right))
            ->orWhere(fn ($query) => $query->where('blocker_id', $right)->where('blocked_id', $left))->exists();
    }

    public static function owned(int $owner, int $target): bool
    {
        return self::query()->where('blocker_id', $owner)->where('blocked_id', $target)->exists();
    }

    public static function setFor(User $actor, int $target, bool $block): void
    {
        abort_if((int) $actor->getKey() === $target, 422);
        DB::transaction(function () use ($actor, $target, $block): void {
            $users = User::query()->whereKey([$actor->getKey(), $target])->orderBy('id')->lockForUpdate()->get()->keyBy('id');
            $owner = $users->get($actor->getKey());
            abort_unless($owner && (string) $owner->status === 'active', 403);
            if ($block) {
                abort_unless($users->has($target), 404);
                static::query()->firstOrCreate(['blocker_id' => $owner->getKey(), 'blocked_id' => $target]);
            } else {
                static::query()->where('blocker_id', $owner->getKey())->where('blocked_id', $target)->delete();
            }
        });
    }

    public static function communicate(int $sender, int $recipient, callable $callback): mixed
    {
        abort_if($sender === $recipient, 422);

        return DB::transaction(function () use ($sender, $recipient, $callback): mixed {
            $users = User::query()->whereKey([$sender, $recipient])->orderBy('id')->lockForUpdate()->get();
            abort_unless($users->count() === 2 && $users->every(fn (User $user) => (string) $user->status === 'active'), 403);
            if (static::between($sender, $recipient)) {
                throw ValidationException::withMessages(['message' => __('conversation::safety.unavailable')]);
            }

            return $callback();
        });
    }

    public static function forOwner(User $owner)
    {
        return self::query()->where('blocker_id', $owner->getKey())->latest('id')->paginate(10);
    }
}
