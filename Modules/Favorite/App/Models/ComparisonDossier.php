<?php

declare(strict_types=1);

namespace Modules\Favorite\App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Modules\Listing\Models\Listing;
use Modules\User\App\Models\User;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

final class ComparisonDossier extends Model
{
    use LogsActivity;
    use SoftDeletes;

    protected $guarded = ['id'];

    protected $hidden = ['share_token', 'token_hash', 'request_key', 'label'];

    private bool $writing = false;

    protected function casts(): array
    {
        return ['user_id' => 'integer', 'category_id' => 'integer', 'listing_ids' => 'array', 'share_token' => 'encrypted', 'sharing' => 'boolean', 'revision' => 'integer', 'expires_at' => 'datetime'];
    }

    protected static function booted(): void
    {
        self::saving(fn (self $record) => abort_unless($record->writing, 403));
        self::deleting(fn (self $record) => abort_unless($record->writing, 403));
    }

    public static function actor(User $user): void
    {
        abort_unless(User::query()->whereKey($user->id)->where('status', 'active')->exists(), 403);
    }

    public static function createFor(User $user, array $input, Collection $selection): self
    {
        self::actor($user);
        $input['label'] = is_string($input['label'] ?? null) ? trim($input['label']) : $input['label'] ?? null;
        $data = Validator::make($input, ['label' => ['required', 'string', 'max:100', 'regex:/\S/u'], 'request_key' => ['required', 'uuid'], 'consent' => ['accepted']])->validate();
        $ids = $selection->pluck('id')->map(fn ($id): int => (int) $id)->unique()->values()->all();
        $category = (int) $selection->first()?->category_id;
        if (count($ids) < 2 || count($ids) > 3 || Listing::dossierListings($ids, $category)->count() !== count($ids)) {
            throw ValidationException::withMessages(['comparison' => __('favorite::dossier.selection')]);
        }

        return DB::transaction(function () use ($user, $data, $ids, $category): self {
            User::query()->whereKey($user->id)->lockForUpdate()->firstOrFail();
            self::actor($user);
            $existing = self::withTrashed()->where('user_id', $user->id)->where('request_key', $data['request_key'])->first();
            if ($existing) {
                if ($existing->label !== $data['label'] || $existing->listing_ids !== $ids || $existing->category_id !== $category || $existing->trashed()) {
                    throw ValidationException::withMessages(['label' => __('favorite::dossier.replay')]);
                }

                return $existing;
            }
            if (self::query()->where('user_id', $user->id)->count() >= 20) {
                throw ValidationException::withMessages(['label' => __('favorite::dossier.limit')]);
            }
            $record = new self;
            $record->writing = true;
            $record->fill(['user_id' => $user->id, 'request_key' => $data['request_key'], 'label' => $data['label'], 'category_id' => $category, 'listing_ids' => $ids, 'sharing' => true, 'revision' => 1]);
            $record->newLink();
            $record->save();
            $record->writing = false;

            return $record;
        });
    }

    private function newLink(): void
    {
        $this->share_token = bin2hex(random_bytes(32));
        $this->token_hash = hash('sha256', $this->share_token);
        $this->expires_at = now()->addDays(30);
    }

    public function listings(): Collection
    {
        $found = Listing::dossierListings($this->listing_ids, $this->category_id)->keyBy('id');

        return collect($this->listing_ids)->map(fn (int $id) => $found->get($id))->filter()->values();
    }

    public static function shared(string $token): self
    {
        abort_unless((bool) preg_match('/^[a-f0-9]{64}$/D', $token), 404);
        $record = self::query()->where('token_hash', hash('sha256', $token))->where('sharing', true)->where('expires_at', '>', now())
            ->whereIn('user_id', User::activeAccountIdsQuery())->firstOrFail();
        abort_unless($record->listings()->count() >= 2, 404);

        return $record;
    }

    public static function forOwner(User $user)
    {
        self::actor($user);

        return self::query()->where('user_id', $user->id)->latest('id')->paginate(12);
    }

    public static function owned(User $user, int $id): self
    {
        self::actor($user);

        return self::query()->where('user_id', $user->id)->findOrFail($id);
    }

    public function changeFor(User $user, string $action, int $revision): void
    {
        self::actor($user);
        abort_unless(in_array($action, ['revoke', 'rotate', 'remove'], true), 422);
        DB::transaction(function () use ($user, $action, $revision): void {
            User::query()->whereKey($user->id)->lockForUpdate()->firstOrFail();
            self::actor($user);
            $record = self::query()->where('user_id', $user->id)->lockForUpdate()->findOrFail($this->id);
            abort_unless($record->revision === $revision, 409);
            $record->writing = true;
            if ($action === 'remove') {
                $record->delete();

                return;
            }
            if ($action === 'rotate') {
                if ($record->listings()->count() < 2) {
                    throw ValidationException::withMessages(['comparison' => __('favorite::dossier.selection')]);
                }
                $record->newLink();
            }
            $record->sharing = $action === 'rotate';
            $record->revision++;
            $record->save();
        });
        if ($action !== 'remove') {
            $this->refresh();
        }
    }

    public function shareUrl(): string
    {
        return route('dossiers.shared', $this->share_token);
    }

    public function stateLabel(): string
    {
        return __('favorite::dossier.'.(! $this->sharing ? 'revoked' : ($this->expires_at->isFuture() ? 'active' : 'expired')));
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logOnly(['user_id', 'sharing', 'revision', 'expires_at', 'deleted_at'])->logOnlyDirty()->dontLogEmptyChanges();
    }
}
