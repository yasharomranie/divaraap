<?php

declare(strict_types=1);

namespace Modules\Notification\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Modules\User\App\Models\User;

final class EmailPreference extends Model
{
    use SoftDeletes;

    public const TYPES = ['offer', 'message', 'review', 'advertising', 'listing', 'promotion', 'saved_search'];

    protected $table = 'notification_email_preferences';

    protected $guarded = [];

    protected $hidden = ['recipient', 'unsubscribe_token', 'unsubscribe_hash'];

    protected function casts(): array
    {
        return ['enabled' => 'boolean', 'recipient' => 'encrypted', 'unsubscribe_token' => 'encrypted', 'types' => 'array', 'version' => 'integer', 'consented_at' => 'datetime'];
    }

    public static function forUser(User $user): ?self
    {
        return self::query()->where('user_id', $user->id)->first();
    }

    public static function eligibleUser(?User $user): bool
    {
        return $user && (string) $user->status === 'active' && $user->email_verified_at !== null && filter_var($user->email, FILTER_VALIDATE_EMAIL) !== false;
    }

    public static function updateFor(User $user, bool $enabled, array $types, int $version, bool $consent, string $locale): self
    {
        abort_unless((int) auth()->id() === (int) $user->id && (string) $user->status === 'active', 403);

        return DB::transaction(function () use ($user, $enabled, $types, $version, $consent, $locale): self {
            $user = User::query()->whereKey($user->id)->lockForUpdate()->firstOrFail();
            abort_unless((string) $user->status === 'active', 403);
            $preference = self::withTrashed()->where('user_id', $user->id)->lockForUpdate()->first() ?? new self(['user_id' => $user->id, 'version' => 0]);
            if ($preference->version !== $version || $preference->trashed()) {
                throw ValidationException::withMessages(['version' => __('notification::email.stale')]);
            }
            if ($enabled && (! self::eligibleUser($user) || ! $consent || $types === [] || array_diff($types, self::TYPES) !== [])) {
                throw ValidationException::withMessages(['consent' => __('notification::email.consent_required')]);
            }
            $token = $enabled ? Str::random(64) : null;
            $preference->fill(['enabled' => $enabled, 'types' => $enabled ? array_values(array_unique($types)) : [], 'recipient' => $enabled ? $user->email : null, 'locale' => $locale === 'en' ? 'en' : 'fa', 'version' => $version + 1, 'unsubscribe_token' => $token, 'unsubscribe_hash' => $token ? hash('sha256', $token) : null, 'consented_at' => $enabled ? now() : null])->save();

            return $preference;
        }, 3);
    }

    public static function unsubscribe(string $token): void
    {
        DB::transaction(function () use ($token): void {
            $preference = self::query()->where('unsubscribe_hash', hash('sha256', $token))->lockForUpdate()->first();
            if ($preference) {
                $preference->fill(['enabled' => false, 'types' => [], 'recipient' => null, 'unsubscribe_token' => null, 'unsubscribe_hash' => null, 'consented_at' => null, 'version' => $preference->version + 1])->save();
            }
        }, 3);
    }

    public function matchesUser(?User $user): bool
    {
        return $this->enabled && $this->consented_at && self::eligibleUser($user) && (int) $this->user_id === (int) $user->id && hash_equals((string) $this->recipient, (string) $user->email);
    }
}
