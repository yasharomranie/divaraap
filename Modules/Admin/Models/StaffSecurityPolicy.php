<?php

declare(strict_types=1);

namespace Modules\Admin\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Modules\User\App\Models\User;

final class StaffSecurityPolicy extends Model
{
    use SoftDeletes;

    public const ROLES = ['support', 'moderator', 'advertising-manager', 'panel_access'];

    protected $guarded = [];

    private bool $writing = false;

    protected function casts(): array
    {
        return ['enabled' => 'boolean', 'roles' => 'array', 'version' => 'integer', 'step_up_minutes' => 'integer'];
    }

    protected static function booted(): void
    {
        self::saving(fn (self $model) => abort_unless($model->writing, 403));
        self::deleting(fn () => abort(403));
    }

    public static function current(): self
    {
        return self::findOrFail(1);
    }

    public function requires(User $user): bool
    {
        return $this->enabled && (string) $user->status === 'active' && ($user->hasRole('admin') || $user->hasRole(array_values(array_intersect($this->roles, self::ROLES))) || in_array('panel_access', $this->roles, true) && $user->can('admin-panel.access'));
    }

    public static function change(bool $enabled, array $roles, int $minutes, int $version, string $password): self
    {
        $actor = User::findOrFail(auth()->id());
        abort_unless((string) $actor->status === 'active' && $actor->hasRole('admin'), 403);
        if (array_diff($roles, self::ROLES) || ! in_array($minutes, [5, 15, 30, 60], true)) {
            throw ValidationException::withMessages(['data.roles' => __('admin::security.invalid')]);
        }
        $bindAfter = false;
        $verifiedSource = null;
        $verifiedAt = null;
        $policy = DB::transaction(function () use ($enabled, $roles, $minutes, $version, $actor, $password, &$bindAfter, &$verifiedSource, &$verifiedAt): self {
            $actor = User::query()->whereKey($actor->id)->lockForUpdate()->firstOrFail();
            abort_unless((string) $actor->status === 'active' && $actor->hasRole('admin'), 403);
            if (! Hash::check($password, $actor->password)) {
                throw ValidationException::withMessages(['data.current_password' => __('admin::security.password_invalid')]);
            }
            $policy = self::query()->whereKey(1)->lockForUpdate()->firstOrFail();
            if ($policy->version !== $version) {
                throw ValidationException::withMessages(['data.version' => __('admin::security.stale')]);
            }
            $proof = app(StaffSecurity::class)->recent($actor, $policy);
            if (($enabled || $policy->enabled) && ! $proof) {
                throw ValidationException::withMessages(['data.enabled' => __('admin::security.prepare_first')]);
            }
            $policy->writing = true;
            $policy->fill(['enabled' => $enabled, 'roles' => array_values(array_unique($roles)), 'step_up_minutes' => $minutes, 'version' => $version + 1, 'updated_by' => $actor->id])->save();
            $policy->writing = false;
            DB::table('staff_security_events')->insert(['user_id' => $actor->id, 'kind' => 'policy_changed', 'created_at' => now(), 'updated_at' => now()]);
            $bindAfter = $proof;
            $verifiedAt = $proof ? session('staff_security.proof.at') : null;
            $verifiedSource = $proof ? app(StaffSecurity::class)->sourceHash($actor) : null;

            return $policy;
        }, 3);
        $fresh = $actor->fresh();
        if ($bindAfter && hash_equals((string) $verifiedSource, (string) app(StaffSecurity::class)->sourceHash($fresh))) {
            app(StaffSecurity::class)->bindProof($fresh, $policy, $verifiedAt);
        }

        return $policy;
    }
}
