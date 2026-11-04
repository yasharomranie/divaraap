<?php

declare(strict_types=1);

namespace Modules\User\App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

final class UserPhoneIdentity extends Model
{
    use SoftDeletes;

    protected $guarded = [];

    protected $casts = ['verified_at' => 'datetime'];

    public static function forPhone(string $phone): ?User
    {
        $id = self::query()->where('phone', $phone)->value('user_id');

        return $id ? User::query()->find($id) : null;
    }

    public static function forUser(User $user): ?self
    {
        return self::query()->where('user_id', $user->id)->first();
    }
}
