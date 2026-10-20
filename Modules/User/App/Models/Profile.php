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

    protected $fillable = ['user_id', 'avatar', 'bio', 'phone', 'city', 'country', 'website', 'is_verified'];

    protected $casts = ['is_verified' => 'boolean', 'shop_enabled' => 'boolean'];

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
