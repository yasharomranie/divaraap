<?php

declare(strict_types=1);

namespace Modules\Conversation\App\Models;

use App\Support\PersianLocale;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Listing\Models\Listing;
use Modules\Notification\Models\UserNotification;
use Modules\User\App\Models\User;

final class ConversationVisit extends Model
{
    use SoftDeletes;

    protected $guarded = ['id'];

    protected $casts = [
        'starts_at' => 'immutable_datetime',
        'ends_at' => 'immutable_datetime',
        'responded_at' => 'immutable_datetime',
        'accepted_at' => 'immutable_datetime',
        'meeting_place' => 'encrypted',
        'note' => 'encrypted',
    ];

    public static function forConversation(Conversation $conversation, int $actorId): LengthAwarePaginator
    {
        abort_unless($conversation->hasParticipant($actorId), 403);

        return self::query()->where('conversation_id', $conversation->id)
            ->where('listing_id', $conversation->listing_id)->where('buyer_id', $conversation->buyer_id)
            ->where('seller_id', $conversation->seller_id)->latest('id')->paginate(10);
    }

    public static function hasUpcomingFor(Conversation $conversation): bool
    {
        return self::query()->where('conversation_id', $conversation->id)
            ->where(fn ($query) => $query->where(fn ($pending) => $pending->where('status', 'pending')->where('starts_at', '>', now()))
                ->orWhere(fn ($accepted) => $accepted->where('status', 'accepted')->where('ends_at', '>', now())))->exists();
    }

    public static function proposeFor(Conversation $conversation, int $actorId, CarbonImmutable $time, string $place, ?string $note): self
    {
        abort_unless($conversation->hasParticipant($actorId), 403);
        $time = $time->setTimezone(config('app.timezone'));
        $target = (int) ((int) $conversation->buyer_id === $actorId ? $conversation->seller_id : $conversation->buyer_id);

        return ConversationBlock::communicate($actorId, $target, function () use ($conversation, $actorId, $time, $place, $note, $target): self {
            $thread = self::lockedThread($conversation, $actorId);
            self::assertAvailableListing($thread);
            if ($time->lessThan(now()->addMinutes(15)) || $time->greaterThan(now()->addDays(90))) {
                throw ValidationException::withMessages(['visit_time' => __('conversation::visits.time_range')]);
            }
            $place = trim($place);
            if ($place === '' || mb_strlen($place) > 200 || ($note !== null && mb_strlen($note) > 500)) {
                throw ValidationException::withMessages(['meeting_place' => __('conversation::visits.invalid_details')]);
            }
            if (self::hasUpcomingFor($thread)) {
                throw ValidationException::withMessages(['visit_time' => __('conversation::visits.existing')]);
            }
            $end = $time->addMinutes(30);
            self::assertNoConflict($thread, $time, $end);
            $visit = self::query()->create([
                'conversation_id' => $thread->id, 'listing_id' => $thread->listing_id,
                'buyer_id' => $thread->buyer_id, 'seller_id' => $thread->seller_id,
                'proposed_by' => $actorId, 'starts_at' => $time->setTimezone(config('app.timezone')),
                'ends_at' => $end->setTimezone(config('app.timezone')), 'status' => 'pending',
                'meeting_place' => $place, 'note' => $note,
            ]);
            $visit->notifyParticipant($target, 'proposed');

            return $visit;
        });
    }

    private static function lockedThread(Conversation $conversation, int $actorId): Conversation
    {
        $thread = Conversation::query()->lockForUpdate()->findOrFail($conversation->id);
        abort_unless($thread->hasParticipant($actorId), 403);
        abort_unless((int) $thread->buyer_id === (int) $conversation->buyer_id
            && (int) $thread->seller_id === (int) $conversation->seller_id
            && (int) $thread->listing_id === (int) $conversation->listing_id, 409);

        return $thread;
    }

    private static function assertAvailableListing(Conversation $conversation): void
    {
        $listing = Listing::query()->lockForUpdate()->find($conversation->listing_id);
        abort_unless($listing && $listing->canPauseDisplay() && (int) $listing->user_id === (int) $conversation->seller_id, 409, __('conversation::visits.unavailable'));
    }

    private static function assertNoConflict(Conversation $conversation, CarbonImmutable $start, CarbonImmutable $end): void
    {
        $pair = [(int) $conversation->buyer_id, (int) $conversation->seller_id];
        $conflict = self::query()->where('status', 'accepted')->where('starts_at', '<', $end)
            ->where('ends_at', '>', $start)
            ->where(fn ($query) => $query->whereIn('buyer_id', $pair)->orWhereIn('seller_id', $pair))->exists();
        if ($conflict) {
            throw ValidationException::withMessages(['visit_time' => __('conversation::visits.conflict')]);
        }
    }

    public function decideFor(Conversation $conversation, int $actorId, string $decision): void
    {
        abort_unless($conversation->hasParticipant($actorId), 403);
        abort_unless(in_array($decision, ['accepted', 'declined', 'cancelled'], true), 422);
        $target = (int) ((int) $conversation->buyer_id === $actorId ? $conversation->seller_id : $conversation->buyer_id);
        $change = function (bool $notify = true) use ($conversation, $actorId, $decision, $target): void {
            $thread = self::lockedThread($conversation, $actorId);
            if ($decision === 'accepted') {
                self::assertAvailableListing($thread);
            }
            $visit = self::query()->lockForUpdate()->findOrFail($this->id);
            abort_unless($visit->matches($thread), 404);
            abort_unless($visit->starts_at->isFuture(), 409, __('conversation::visits.past'));
            if ($decision === 'cancelled') {
                abort_unless(in_array($visit->status, ['pending', 'accepted'], true), 409, __('conversation::visits.stale'));
            } else {
                abort_if((int) $visit->proposed_by === $actorId, 403);
                abort_unless($visit->status === 'pending', 409, __('conversation::visits.stale'));
            }
            if ($decision === 'accepted') {
                self::assertNoConflict($thread, $visit->starts_at, $visit->ends_at);
                $visit->accepted_at = now();
            }
            $visit->forceFill(['status' => $decision, 'responded_by' => $actorId, 'responded_at' => now()])->save();
            if ($notify) {
                $visit->notifyParticipant($target, $decision);
            }
            $this->setRawAttributes($visit->getAttributes())->syncOriginal();
        };
        if ($decision === 'cancelled') {
            DB::transaction(function () use ($actorId, $target, $change): void {
                $users = User::query()->whereKey([$actorId, $target])->orderBy('id')->lockForUpdate()->get()->keyBy('id');
                $actor = $users->get($actorId);
                abort_unless($actor && (string) $actor->status === 'active', 403);
                $peer = $users->get($target);
                $change($peer && (string) $peer->status === 'active' && ! ConversationBlock::between($actorId, $target));
            });
        } else {
            ConversationBlock::communicate($actorId, $target, $change);
        }
    }

    public function matches(Conversation $conversation): bool
    {
        return (int) $this->conversation_id === (int) $conversation->id
            && (int) $this->buyer_id === (int) $conversation->buyer_id
            && (int) $this->seller_id === (int) $conversation->seller_id
            && (int) $this->listing_id === (int) $conversation->listing_id;
    }

    public function displayStatus(): string
    {
        return ($this->status === 'pending' && ! $this->starts_at->isFuture()) || ($this->status === 'accepted' && ! $this->ends_at->isFuture()) ? 'past' : (string) $this->status;
    }

    private function notifyParticipant(int $target, string $event): void
    {
        UserNotification::publish($target, UserNotification::TYPE_MESSAGE,
            __('conversation::visits.notification_'.$event),
            __('conversation::visits.notification_body', ['time' => PersianLocale::date($this->starts_at->setTimezone('Asia/Tehran'), true)]),
            route('conversations.visits.index', ['conversation' => $this->conversation_id]));
    }
}
