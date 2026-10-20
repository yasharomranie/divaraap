<?php

declare(strict_types=1);

namespace Modules\User\App\Models;

use App\Support\IranianPhone;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

class Profile extends Model
{
    use LogsActivity, SoftDeletes;

    protected $fillable = ['user_id', 'avatar', 'bio', 'phone', 'city', 'country', 'website'];

    protected $casts = ['is_verified' => 'boolean', 'shop_enabled' => 'boolean', 'verified_at' => 'datetime'];

    public static function verificationIdentity(User $user, ?self $profile): string
    {
        return hash('sha256', json_encode([
            $user->getKey(), $user->name, $user->email, trim((string) $profile?->phone), trim((string) $profile?->shop_name),
        ], JSON_THROW_ON_ERROR));
    }

    public static function verificationVersion(User $user): string
    {
        $profile = static::detailsForUser($user);

        return hash('sha256', json_encode([
            static::verificationIdentity($user, $profile), $profile?->is_verified,
            $profile?->verified_at?->toIso8601String(), $profile?->verified_by, $profile?->verification_fingerprint,
        ], JSON_THROW_ON_ERROR));
    }

    public static function verificationFor(User $user): array
    {
        $profile = $user->relationLoaded('profile') ? $user->getRelation('profile') : static::detailsForUser($user);
        $active = (string) $user->status === 'active';
        $reviewed = $active && $profile?->is_verified && $profile?->verified_at !== null
            && is_string($profile?->verification_fingerprint)
            && hash_equals(static::verificationIdentity($user, $profile), $profile->verification_fingerprint);

        return ['email' => $active && $user->email_verified_at !== null, 'reviewed' => (bool) $reviewed, 'reviewed_at' => $reviewed ? $profile->verified_at : null];
    }

    public static function publicVerification(int $userId): array
    {
        $user = User::query()->with('profile')->find($userId);

        return $user ? static::verificationFor($user) : ['email' => false, 'reviewed' => false, 'reviewed_at' => null];
    }

    public static function canManageVerification(User $actor, User $subject): bool
    {
        return (string) $actor->status === 'active' && $actor->hasRole('admin') && $actor->getKey() !== $subject->getKey();
    }

    public static function setVerificationFor(User $actor, User $subject, bool $grant, string $reason, string $version): void
    {
        $reason = trim($reason);
        if (mb_strlen($reason) < 3 || mb_strlen($reason) > 1000) {
            throw ValidationException::withMessages(['reason' => __('user::verification.reason_error')]);
        }
        DB::transaction(function () use ($actor, $subject, $grant, $reason, $version): void {
            $users = User::query()->whereKey([$actor->getKey(), $subject->getKey()])->orderBy('id')->lockForUpdate()->get()->keyBy('id');
            $reviewer = $users->get($actor->getKey());
            $owner = $users->get($subject->getKey());
            abort_unless($reviewer && $owner && static::canManageVerification($reviewer, $owner), 403);
            abort_if($grant && (string) $owner->status !== 'active', 422);
            if (! hash_equals(static::verificationVersion($owner), $version)) {
                throw ValidationException::withMessages(['version' => __('user::verification.conflict')]);
            }
            $profile = static::query()->firstOrNew(['user_id' => $owner->getKey()]);
            $profile->forceFill([
                'is_verified' => $grant,
                'verified_at' => $grant ? now() : null,
                'verified_by' => $grant ? $reviewer->getKey() : null,
                'verification_fingerprint' => $grant ? static::verificationIdentity($owner, $profile) : null,
            ])->save();
            activity('seller-verification')->performedOn($owner)->causedBy($reviewer)
                ->withProperties(['decision' => $grant ? 'grant' : 'revoke', 'reason' => $reason])
                ->log($grant ? 'seller_review_granted' : 'seller_review_revoked');
        });
    }

    public static function shopFor(User $user): array
    {
        $profile = static::detailsForUser($user);

        return [
            'shop_enabled' => (bool) $profile?->getAttribute('shop_enabled'),
            'shop_name' => (string) $profile?->getAttribute('shop_name'),
            'shop_description' => (string) $profile?->getAttribute('shop_description'),
            'shop_hours' => (string) $profile?->getAttribute('shop_hours'),
        ];
    }

    public static function shopVersion(User $user, ?array $shop = null): string
    {
        return hash('sha256', json_encode($shop ?? static::shopFor($user), JSON_THROW_ON_ERROR));
    }

    public static function saveShopFor(User $user, array $data, string $version): void
    {
        DB::transaction(function () use ($user, $data, $version): void {
            $owner = User::query()->lockForUpdate()->findOrFail($user->getKey());
            abort_unless((string) $owner->status === 'active', 403);
            if (! hash_equals(static::shopVersion($owner), $version)) {
                throw ValidationException::withMessages(['version' => __('user::shop.conflict')]);
            }
            $profile = static::query()->firstOrNew(['user_id' => $owner->getKey()]);
            $profile->forceFill([
                'shop_enabled' => (bool) $data['shop_enabled'],
                'shop_name' => trim($data['shop_name'] ?? ''),
                'shop_description' => trim($data['shop_description'] ?? ''),
                'shop_hours' => trim($data['shop_hours'] ?? ''),
            ])->save();
        });
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logFillable()
            ->logOnlyDirty()
            ->dontLogEmptyChanges();
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public static function detailsForUser(User $user): ?self
    {
        return static::query()
            ->where('user_id', $user->getKey())
            ->first();
    }

    public static function savePhoneForUser(User $user, ?string $phone): void
    {
        static::query()->updateOrCreate(['user_id' => $user->getKey()], ['phone' => $phone === null ? null : IranianPhone::normalize($phone)]);
    }

    public static function phoneForUser(User $user): ?string
    {
        return static::query()
            ->where('user_id', $user->getKey())
            ->value('phone');
    }
}
