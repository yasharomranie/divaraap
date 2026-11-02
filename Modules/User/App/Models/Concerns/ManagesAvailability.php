<?php

declare(strict_types=1);

namespace Modules\User\App\Models\Concerns;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Modules\User\App\Models\User;

trait ManagesAvailability
{
    public static function availabilityReasons(): array
    {
        return ['delayed', 'limited', 'break'];
    }

    public static function availabilityRules(): array
    {
        return [
            'enabled' => ['required', 'boolean'],
            'until' => ['exclude_unless:enabled,1', 'required', 'string', 'date_format:Y-m-d\TH:i'],
            'reason' => ['exclude_unless:enabled,1', 'required', Rule::in(self::availabilityReasons())],
            'public_consent' => ['exclude_unless:enabled,1', 'accepted'],
            'revision' => ['required', 'integer', 'min:0'],
        ];
    }

    public static function availabilityFor(User $user): array
    {
        abort_unless(User::query()->whereKey($user->id)->where('status', 'active')->exists(), 403);
        $profile = self::detailsForUser($user);
        $enabled = (bool) $profile?->away_enabled;
        $active = $enabled && $profile?->away_until?->isFuture();

        return ['enabled' => $enabled, 'active' => $active, 'until' => $profile?->away_until?->setTimezone('Asia/Tehran')->format('Y-m-d\TH:i') ?? '', 'reason' => $profile?->away_reason ?? 'delayed', 'revision' => (int) ($profile?->away_revision ?? 0)];
    }

    public static function publicAvailability(int $userId): ?array
    {
        $profile = self::query()->where('user_id', $userId)->where('away_enabled', true)->where('away_until', '>', now())
            ->whereIn('user_id', User::activeAccountIdsQuery()->whereKey($userId))->first(['away_until', 'away_reason']);
        if (! $profile) {
            return null;
        }

        return ['until' => CarbonImmutable::instance($profile->away_until)->setTimezone('Asia/Tehran'), 'reason' => in_array($profile->away_reason, self::availabilityReasons(), true) ? $profile->away_reason : 'delayed'];
    }

    public static function saveAvailabilityFor(User $user, array $data): void
    {
        $data = Validator::make($data, self::availabilityRules())->validate();
        $enabled = (bool) $data['enabled'];
        $until = $enabled ? CarbonImmutable::createFromFormat('!Y-m-d\TH:i', $data['until'], 'Asia/Tehran')->setTimezone(config('app.timezone')) : null;
        if ($enabled && (! $until->isFuture() || $until->greaterThan(now()->addDays(30)))) {
            throw ValidationException::withMessages(['until' => __('user::availability.date_error')]);
        }
        DB::transaction(function () use ($user, $data, $enabled, $until): void {
            $owner = User::query()->lockForUpdate()->findOrFail($user->id);
            abort_unless((string) $owner->status === 'active', 403);
            $profile = self::query()->where('user_id', $owner->id)->lockForUpdate()->first();
            $revision = (int) ($profile?->away_revision ?? 0);
            if ($revision !== (int) $data['revision']) {
                throw ValidationException::withMessages(['revision' => __('user::availability.stale')]);
            }
            $profile ??= new self(['user_id' => $owner->id]);
            $profile->forceFill(['away_enabled' => $enabled, 'away_until' => $until, 'away_reason' => $enabled ? $data['reason'] : null, 'away_revision' => $revision + 1])->save();
            activity('seller-availability')->performedOn($profile)->causedBy($owner)->withProperties(['enabled' => $enabled, 'revision' => $revision + 1])->log($enabled ? 'enabled' : 'disabled');
        });
    }
}
