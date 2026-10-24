<?php

declare(strict_types=1);

namespace Modules\Favorite\App\Models\Concerns;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Modules\Favorite\App\Models\FavoriteListing;
use Modules\Listing\Models\Listing;
use Modules\User\App\Models\User;

trait SharesShortlist
{
    public static function sharingActor(User $user): void
    {
        abort_unless(User::query()->whereKey($user->id)->where('status', 'active')->exists(), 403);
    }

    public function sharingPreview(User $user, bool $lock = false): array
    {
        self::sharingActor($user);
        abort_unless((int) $this->user_id === (int) $user->id && ! $this->trashed(), 404);
        $query = FavoriteListing::query()->where('user_id', $user->id)->where('collection_id', $this->id)->orderBy('id')->limit(51);
        if ($lock) {
            $query->lockForUpdate();
        }
        $members = $query->pluck('listing_id')->map(fn ($id): int => (int) $id)->all();
        $found = count($members) <= 50 ? Listing::sharedShortlistListings($members)->keyBy('id') : collect();
        $listings = collect($members)->map(fn (int $id) => $found->get($id))->filter()->values();

        return ['listings' => $listings, 'valid' => count($members) <= 50 && $listings->isNotEmpty(), 'signature' => hash('sha256', json_encode([$this->id, $this->user_id, $members, $listings->pluck('id')->all()], JSON_THROW_ON_ERROR))];
    }

    public function sharedItems(): Collection
    {
        if ($this->trashed()) {
            return collect();
        }
        $members = FavoriteListing::query()->where('user_id', $this->user_id)->where('collection_id', $this->id)->whereIn('listing_id', $this->share_ids ?? [])->pluck('listing_id')->map(fn ($id): int => (int) $id)->all();
        $found = Listing::sharedShortlistListings($members)->keyBy('id');

        return collect($this->share_ids ?? [])->map(fn (int $id) => $found->get($id))->filter()->values();
    }

    public function changeSharingFor(User $user, array $input): void
    {
        self::sharingActor($user);
        $data = Validator::make($input, [
            'action' => ['required', Rule::in(['create', 'refresh', 'rotate', 'revoke', 'clear'])],
            'share_revision' => ['required', 'integer', 'min:0'],
            'consent' => ['required_if:action,create,refresh', 'accepted_if:action,create,refresh'],
            'signature' => ['required_if:action,create,refresh', 'nullable', 'string', 'regex:/^[a-f0-9]{64}$/D'],
        ])->validate();
        DB::transaction(function () use ($user, $data): void {
            User::query()->whereKey($user->id)->lockForUpdate()->firstOrFail();
            self::sharingActor($user);
            $record = self::query()->where('user_id', $user->id)->lockForUpdate()->findOrFail($this->id);
            abort_unless($record->share_revision === (int) $data['share_revision'], 409);
            $action = $data['action'];
            if (in_array($action, ['create', 'refresh'], true)) {
                abort_unless($action === 'create' ? $record->share_token === null : $record->share_token !== null, 409);
                $preview = $record->sharingPreview($user, true);
                if (! $preview['valid'] || ! hash_equals($preview['signature'], $data['signature'])) {
                    throw ValidationException::withMessages(['signature' => __('favorite::sharing.changed_selection')]);
                }
                $record->share_ids = $preview['listings']->pluck('id')->all();
            } elseif ($action === 'rotate') {
                abort_unless($record->share_token !== null, 409);
                if ($record->sharedItems()->isEmpty()) {
                    throw ValidationException::withMessages(['signature' => __('favorite::sharing.empty_selection')]);
                }
            }
            if (in_array($action, ['create', 'refresh', 'rotate'], true)) {
                $record->share_token = bin2hex(random_bytes(32));
                $record->share_hash = hash('sha256', $record->share_token);
                $record->share_expires_at = now()->addDays(30);
                $record->share_enabled = true;
            } else {
                $record->share_enabled = false;
                if ($action === 'clear') {
                    $record->share_token = null;
                    $record->share_hash = null;
                    $record->share_ids = null;
                    $record->share_expires_at = null;
                }
            }
            $record->share_revision++;
            $record->save();
            activity('shortlist-sharing')->performedOn($record)->causedBy($user)->withProperties(['decision' => $action, 'revision' => $record->share_revision])->log('shortlist_'.$action);
        });
        $this->refresh();
    }

    public static function sharedShortlist(string $token): self
    {
        abort_unless((bool) preg_match('/^[a-f0-9]{64}$/D', $token), 404);
        $record = self::query()->where('share_hash', hash('sha256', $token))->where('share_enabled', true)->where('share_expires_at', '>', now())
            ->whereIn('user_id', User::activeAccountIdsQuery())->firstOrFail();
        abort_if($record->sharedItems()->isEmpty(), 404);

        return $record;
    }

    public function shortlistUrl(): string
    {
        return route('shortlists.shared', $this->share_token);
    }

    public function sharingState(): string
    {
        return __('favorite::sharing.'.(! $this->share_token ? 'none' : (! $this->share_enabled ? 'revoked' : ($this->share_expires_at->isFuture() ? 'active' : 'expired'))));
    }
}
