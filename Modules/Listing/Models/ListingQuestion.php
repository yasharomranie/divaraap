<?php

declare(strict_types=1);

namespace Modules\Listing\Models;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Modules\Category\Models\Category;
use Modules\Notification\Models\UserNotification;
use Modules\User\App\Models\User;

final class ListingQuestion extends Model
{
    use SoftDeletes;

    protected $guarded = ['id'];

    protected $attributes = ['state' => 'pending', 'revision' => 1];

    protected function casts(): array
    {
        return ['revision' => 'integer', 'reviewed_at' => 'datetime'];
    }

    public static function publicListing(Listing $listing): bool
    {
        return ! $listing->trashed() && $listing->statusValue() === 'active' && (! $listing->expires_at || $listing->expires_at->isFuture())
            && isset(Category::advertisingFilterOptions()[$listing->category_id])
            && User::query()->whereKey($listing->user_id)->where('status', 'active')->exists();
    }

    public static function canModerate(): bool
    {
        $user = auth()->user();

        return $user && (string) $user->status === 'active' && ($user->hasRole('admin') || $user->can('moderation.listings'));
    }

    public static function publishedFor(Listing $listing): LengthAwarePaginator
    {
        abort_unless(self::publicListing($listing), 404);

        return self::query()->where('listing_id', $listing->id)->where('state', 'published')->where('answered_by', $listing->user_id)
            ->whereIn('asker_id', User::activeAccountIdsQuery())->latest('id')->paginate(15);
    }

    public static function forUser(User $user, bool $received): LengthAwarePaginator
    {
        abort_unless((string) $user->status === 'active', 403);

        return self::query()->when($received, fn ($query) => $query->whereIn('listing_id', Listing::query()->ownedByUser($user->id)->select('id')), fn ($query) => $query->where('asker_id', $user->id))->latest('id')->paginate(15)->withQueryString();
    }

    public static function ask(User $user, Listing $listing, string $question, bool $consent): self
    {
        abort_unless((string) $user->status === 'active', 403);
        $question = trim((string) preg_replace('/\s+/u', ' ', $question));
        Validator::make(['question' => $question, 'consent' => $consent], ['question' => ['required', 'string', 'min:10', 'max:600', 'regex:/\S/u'], 'consent' => ['accepted']])->validate();

        return DB::transaction(function () use ($user, $listing, $question): self {
            $listing = Listing::query()->lockForUpdate()->findOrFail($listing->id);
            abort_unless(self::publicListing($listing), 404);
            abort_if((int) $listing->user_id === (int) $user->id, 403);
            $fingerprint = hash('sha256', $question);
            $existing = self::query()->where('listing_id', $listing->id)->where('asker_id', $user->id)->where('fingerprint', $fingerprint)->first();
            if ($existing) {
                return $existing;
            }
            if (self::query()->where('listing_id', $listing->id)->where('asker_id', $user->id)->whereIn('state', ['pending', 'answered'])->count() >= 3) {
                throw ValidationException::withMessages(['question' => __('listing::questions.open_limit')]);
            }
            $record = self::create(['listing_id' => $listing->id, 'asker_id' => $user->id, 'fingerprint' => $fingerprint, 'question' => $question]);
            UserNotification::publish((int) $listing->user_id, UserNotification::TYPE_LISTING, __('listing::questions.new_notice'), null, route('listing-questions.mine', ['role' => 'received']));

            return $record;
        });
    }

    public function answerFor(User $user, string $answer, int $revision): void
    {
        $answer = trim($answer);
        Validator::make(['answer' => $answer], ['answer' => ['required', 'string', 'min:2', 'max:1200', 'regex:/\S/u']])->validate();
        DB::transaction(function () use ($user, $answer, $revision): void {
            $listing = Listing::query()->lockForUpdate()->findOrFail($this->listing_id);
            $record = self::query()->lockForUpdate()->findOrFail($this->id);
            abort_unless((string) $user->status === 'active' && (int) $listing->user_id === (int) $user->id, 403);
            abort_unless(self::publicListing($listing), 404);
            $record->checkRevision($revision);
            $record->forceFill(['answer' => trim($answer), 'answered_by' => $user->id, 'state' => 'answered', 'reviewer_id' => null, 'review_note' => null, 'reviewed_at' => null, 'revision' => $record->revision + 1])->save();
            $record->audit($user, 'answered');
        });
    }

    public function withdrawFor(User $user, int $revision): void
    {
        DB::transaction(function () use ($user, $revision): void {
            Listing::withTrashed()->whereKey($this->listing_id)->lockForUpdate()->firstOrFail();
            $record = self::query()->lockForUpdate()->findOrFail($this->id);
            abort_unless((string) $user->status === 'active' && (int) $record->asker_id === (int) $user->id, 403);
            $record->checkRevision($revision);
            $record->audit($user, 'withdrawn');
            $record->delete();
        });
    }

    public function moderate(bool $publish, ?string $note, int $revision): void
    {
        abort_unless(self::canModerate(), 403);
        $note = $note === null ? null : trim($note);
        Validator::make(['note' => $note], ['note' => [$publish ? 'nullable' : 'required', 'string', 'max:1000']])->validate();
        DB::transaction(function () use ($publish, $note, $revision): void {
            $listing = Listing::withTrashed()->whereKey($this->listing_id)->lockForUpdate()->firstOrFail();
            $record = self::query()->lockForUpdate()->findOrFail($this->id);
            $user = auth()->user();
            abort_if(in_array((int) $user->id, [(int) $listing->user_id, (int) $record->asker_id], true), 403);
            $record->checkRevision($revision);
            if ($publish && ($record->state !== 'answered' || ! filled($record->answer) || (int) $record->answered_by !== (int) $listing->user_id || ! self::publicListing($listing) || ! User::query()->whereKey($record->asker_id)->where('status', 'active')->exists())) {
                throw ValidationException::withMessages(['revision' => __('listing::questions.unavailable')]);
            }
            if (! $publish && $record->state === 'rejected') {
                throw ValidationException::withMessages(['revision' => __('listing::questions.stale')]);
            }
            $record->forceFill(['state' => $publish ? 'published' : 'rejected', 'reviewer_id' => $user->id, 'review_note' => $note, 'reviewed_at' => now(), 'revision' => $record->revision + 1])->save();
            $record->audit($user, $record->state);
            if ($publish) {
                UserNotification::publish((int) $record->asker_id, UserNotification::TYPE_LISTING, __('listing::questions.published_notice'), null, route('listing-questions.mine'));
            }
        });
    }

    public function stateLabel(): string
    {
        return __('listing::questions.'.$this->state);
    }

    private function checkRevision(int $revision): void
    {
        if ($this->revision !== $revision) {
            throw ValidationException::withMessages(['revision' => __('listing::questions.stale')]);
        }
    }

    private function audit(User $user, string $decision): void
    {
        activity('listing-question')->performedOn($this)->causedBy($user)->withProperties(['decision' => $decision, 'revision' => $this->revision])->log($decision);
    }
}
