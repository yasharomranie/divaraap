<?php

declare(strict_types=1);

namespace Modules\Conversation\App\Models;

use App\Support\PersianLocale;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Validation\ValidationException;
use Modules\Listing\Models\Listing;
use Modules\User\App\Models\User;

class Conversation extends Model
{
    use HasFactory, SoftDeletes;

    public static function reviewEvidenceFor(int $buyerId, int $sellerId, ?int $listingId): ?int
    {
        if ($buyerId === $sellerId) {
            return null;
        }

        return static::query()->where('buyer_id', $buyerId)->where('seller_id', $sellerId)
            ->when($listingId !== null, fn (Builder $query) => $query->where('listing_id', $listingId))
            ->where('created_at', '<=', now())
            ->whereHas('messages', fn (Builder $query) => $query->where('sender_id', $buyerId)->where('created_at', '<=', now())->whereColumn('created_at', '>=', 'conversations.created_at'))
            ->whereHas('messages', fn (Builder $query) => $query->where('sender_id', $sellerId)->where('created_at', '<=', now())->whereColumn('created_at', '>=', 'conversations.created_at'))
            ->latest('id')->value('id');
    }

    public static function sellerResponseSummary(int $sellerId): ?array
    {
        if (! User::query()->whereKey($sellerId)->where('status', 'active')->exists()) {
            return null;
        }
        $end = now();
        $threads = static::query()->where('seller_id', $sellerId)->where('buyer_id', '!=', $sellerId)
            ->whereBetween('created_at', [$end->copy()->subDays(90), $end])
            ->select(['id', 'buyer_id'])
            ->addSelect(['first_buyer_id' => ConversationMessage::query()->select('id')
                ->whereColumn('conversation_id', 'conversations.id')->whereColumn('sender_id', 'conversations.buyer_id')
                ->whereColumn('created_at', '>=', 'conversations.created_at')->where('created_at', '<=', $end)
                ->orderBy('created_at')->orderBy('id')->limit(1)])
            ->whereHas('messages', fn (Builder $query) => $query->whereColumn('sender_id', 'conversations.buyer_id')
                ->whereColumn('created_at', '>=', 'conversations.created_at')->where('created_at', '<=', $end))
            ->latest('created_at')->orderByDesc('id')->limit(100)->get();
        if ($threads->count() < 5 || $threads->pluck('buyer_id')->unique()->count() < 3) {
            return null;
        }
        $questions = ConversationMessage::query()->whereKey($threads->pluck('first_buyer_id')->all())
            ->get(['id', 'conversation_id', 'created_at']);
        if ($questions->count() < 5) {
            return null;
        }
        $replies = ConversationMessage::query()->where('sender_id', $sellerId)->where('created_at', '<=', $end)
            ->where(function (Builder $query) use ($questions): void {
                foreach ($questions as $question) {
                    $query->orWhere(fn (Builder $thread) => $thread->where('conversation_id', $question->conversation_id)
                        ->where(fn (Builder $after) => $after->where('created_at', '>', $question->created_at)
                            ->orWhere(fn (Builder $same) => $same->where('created_at', $question->created_at)->where('id', '>', $question->id))));
                }
            })->select('conversation_id')->selectRaw('MIN(created_at) as replied_at')->groupBy('conversation_id')->get()->keyBy('conversation_id');
        $durations = [];
        $buyers = [];
        foreach ($questions as $question) {
            $reply = $replies->get($question->conversation_id);
            if ($reply) {
                $durations[] = max(0, CarbonImmutable::parse($reply->replied_at)->timestamp - $question->created_at->timestamp);
                $buyers[] = $threads->firstWhere('id', $question->conversation_id)->buyer_id;
            }
        }
        if (count($durations) < 5 || count(array_unique($buyers)) < 3) {
            return null;
        }
        sort($durations);
        $middle = intdiv(count($durations), 2);
        $median = count($durations) % 2 ? $durations[$middle] : ($durations[$middle - 1] + $durations[$middle]) / 2;

        return ['seconds' => (int) ceil($median), 'answered' => count($durations), 'total' => $threads->count(), 'rate' => (int) round(count($durations) / $threads->count() * 100)];
    }

    public static function sellerResponseLabel(array $summary): string
    {
        $seconds = $summary['seconds'];
        if ($seconds < 60) {
            return __('conversation::response.under_minute');
        }
        $unit = $seconds < 3600 ? 'minutes' : ($seconds < 86400 ? 'hours' : 'days');
        $divisor = match ($unit) {
            'minutes' => 60, 'hours' => 3600, default => 86400
        };

        return trans_choice('conversation::response.'.$unit, (int) ceil($seconds / $divisor), ['count' => PersianLocale::number((int) ceil($seconds / $divisor))]);
    }

    public static function performanceCount(int $listingId, int $sellerId, CarbonImmutable $start, CarbonImmutable $end): int
    {
        return static::query()->where('listing_id', $listingId)->where('seller_id', $sellerId)
            ->whereBetween('created_at', [$start, $end])->count();
    }

    protected $fillable = ['listing_id', 'seller_id', 'buyer_id', 'last_message_at'];

    protected $casts = ['last_message_at' => 'datetime'];

    public static function pollForUser(int $userId, ?int $conversationId, int $after): array
    {
        $unreadTotal = static::unreadCountForUser($userId);
        $messages = [];
        if ($conversationId !== null) {
            $conversation = static::query()->findOrFail($conversationId);
            abort_unless($conversation->hasParticipant($userId), 403);
            $messages = $conversation->messagesAfter($after)->map(fn (ConversationMessage $message): array => [
                'conversation' => ['id' => (int) $conversation->getKey()],
                'message' => $message->toRealtimePayloadFor($userId),
                'counts' => ['unread_messages_total' => $unreadTotal],
            ])->all();
        }

        return ['messages' => $messages, 'unread_total' => $unreadTotal];
    }

    public function messagesAfter(int $messageId): Collection
    {
        return $this->messages()->with('sender:id,name')->where('id', '>', $messageId)->orderBy('id')->limit(100)->get();
    }

    public function listing()
    {
        return $this->belongsTo(Listing::class);
    }

    public function seller()
    {
        return $this->belongsTo(User::class, 'seller_id');
    }

    public function buyer()
    {
        return $this->belongsTo(User::class, 'buyer_id');
    }

    public function messages()
    {
        return $this->hasMany(ConversationMessage::class);
    }

    public function lastMessage()
    {
        return $this->hasOne(ConversationMessage::class)
            ->latestOfMany()
            ->select([
                'conversation_messages.id',
                'conversation_messages.conversation_id',
                'conversation_messages.sender_id',
                'conversation_messages.body',
                'conversation_messages.created_at',
            ]);
    }

    public function scopeForUser(Builder $query, int $userId): Builder
    {
        return $query->where(function (Builder $participantQuery) use ($userId): void {
            $participantQuery->where('buyer_id', $userId)->orWhere('seller_id', $userId);
        });
    }

    public function scopeApplyMessageFilter(Builder $query, int $userId, string $messageFilter): Builder
    {
        if (! in_array($messageFilter, ['unread', 'important'], true)) {
            return $query;
        }

        return $query->whereHas('messages', function (Builder $messageQuery) use ($userId): void {
            $messageQuery->where('sender_id', '!=', $userId)->whereNull('read_at');
        });
    }

    public function scopeWithInboxData(Builder $query, int $userId): Builder
    {
        return $query
            ->with([
                'listing:id,title,slug,price,currency,user_id,status,expires_at',
                'buyer:id,name',
                'seller:id,name',
                'lastMessage',
                'lastMessage.sender:id,name',
            ])
            ->withCount([
                'messages as unread_count' => fn (Builder $messageQuery) => $messageQuery
                    ->where('sender_id', '!=', $userId)
                    ->whereNull('read_at'),
            ])
            ->orderByDesc('last_message_at')
            ->orderByDesc('updated_at');
    }

    public static function inboxForUser(int $userId, string $messageFilter = 'all'): EloquentCollection
    {
        return static::query()
            ->forUser($userId)
            ->applyMessageFilter($userId, $messageFilter)
            ->withInboxData($userId)
            ->get();
    }

    public static function resolveSelected(EloquentCollection $conversations, ?int $conversationId): ?self
    {
        $selectedConversationId = $conversationId;

        if (($selectedConversationId ?? 0) <= 0 && $conversations->isNotEmpty()) {
            $selectedConversationId = (int) $conversations->first()->getKey();
        }

        if (($selectedConversationId ?? 0) <= 0) {
            return null;
        }

        $selected = $conversations->firstWhere('id', $selectedConversationId);

        if (! $selected instanceof self) {
            return null;
        }

        return $selected;
    }

    public function loadThread(): void
    {
        $this->load([
            'listing:id,title,slug,price,currency,user_id,status,expires_at',
            'messages' => fn ($query) => $query->with('sender:id,name')->ordered(),
        ]);
    }

    public function hasParticipant(int $userId): bool
    {
        return (int) $this->buyer_id === $userId || (int) $this->seller_id === $userId;
    }

    public function participantIds(): array
    {
        return collect([$this->buyer_id, $this->seller_id])
            ->filter()
            ->map(fn ($id): int => (int) $id)
            ->unique()
            ->values()
            ->all();
    }

    public function partnerFor(int $userId): ?User
    {
        $this->loadMissing([
            'buyer:id,name,email',
            'seller:id,name,email',
        ]);

        return (int) $this->buyer_id === $userId ? $this->seller : $this->buyer;
    }

    public function createMessageFor(int $senderId, string $body): ConversationMessage
    {
        abort_unless($this->hasParticipant($senderId), 403);
        $body = trim($body);
        if ($body === '' || mb_strlen($body) > 2000) {
            throw ValidationException::withMessages(['message' => __('conversation::messages.empty_message')]);
        }
        $recipient = (int) ((int) $this->buyer_id === $senderId ? $this->seller_id : $this->buyer_id);

        return ConversationBlock::communicate($senderId, $recipient, function () use ($senderId, $recipient, $body): ConversationMessage {
            $thread = static::query()->lockForUpdate()->findOrFail($this->getKey());
            abort_unless($thread->hasParticipant($senderId) && $thread->hasParticipant($recipient), 409);
            $message = $thread->messages()->create(['sender_id' => $senderId, 'body' => $body]);
            $thread->forceFill(['last_message_at' => $message->created_at])->save();
            $this->setRawAttributes($thread->getAttributes())->syncOriginal();

            return $message->loadMissing('sender:id,name');
        });
    }

    public function unreadCountForParticipant(int $userId): int
    {
        return (int) ConversationMessage::query()
            ->where('conversation_id', $this->getKey())
            ->where('sender_id', '!=', $userId)
            ->whereNull('read_at')
            ->count();
    }

    public function markAsReadFor(int $userId): int
    {
        return ConversationMessage::query()
            ->where('conversation_id', $this->getKey())
            ->where('sender_id', '!=', $userId)
            ->whereNull('read_at')
            ->update([
                'read_at' => now(),
                'updated_at' => now(),
            ]);
    }

    public function listingImageUrl(): ?string
    {
        $this->loadMissing('listing');

        $url = $this->listing?->primaryImageUrl('thumb', 'desktop');

        return is_string($url) && trim($url) !== '' ? $url : null;
    }

    public function summaryPayloadFor(int $viewerId): array
    {
        $this->loadMissing([
            'listing:id,title,slug,price,currency,user_id,status,expires_at',
            'buyer:id,name,email',
            'seller:id,name,email',
            'lastMessage',
            'lastMessage.sender:id,name',
        ]);

        $lastMessage = $this->lastMessage;
        $partner = $this->partnerFor($viewerId);

        return [
            'id' => (int) $this->getKey(),
            'unread_count' => $this->unread_count ?? $this->unreadCountForParticipant($viewerId),
            'listing' => [
                'id' => (int) ($this->listing?->getKey() ?? 0),
                'title' => (string) ($this->listing?->title ?? 'Listing removed'),
                'image_url' => $this->listingImageUrl(),
            ],
            'partner' => [
                'id' => (int) ($partner?->getKey() ?? 0),
                'name' => (string) ($partner?->name ?? 'User'),
            ],
            'last_message' => $lastMessage
                ? $lastMessage->toRealtimePayloadFor($viewerId)
                : null,
            'last_message_at' => $this->last_message_at?->toIso8601String(),
        ];
    }

    public function realtimePayloadFor(int $viewerId, ConversationMessage $message): array
    {
        $summary = $this->summaryPayloadFor($viewerId);

        return [
            'conversation' => [
                'id' => (int) $this->getKey(),
                'unread_count' => $this->unreadCountForParticipant($viewerId),
            ],
            'listing' => $summary['listing'],
            'partner' => $summary['partner'],
            'message' => $message->toRealtimePayloadFor($viewerId),
            'counts' => [
                'unread_messages_total' => static::unreadCountForUser($viewerId),
            ],
        ];
    }

    public function readPayloadFor(int $viewerId): array
    {
        return [
            'conversation' => [
                'id' => (int) $this->getKey(),
                'unread_count' => $this->unreadCountForParticipant($viewerId),
            ],
            'counts' => [
                'unread_messages_total' => static::unreadCountForUser($viewerId),
            ],
        ];
    }

    public static function openForListingBuyer(Listing $listing, int $buyerId): self
    {
        $sellerId = (int) $listing->user_id;

        return ConversationBlock::communicate($buyerId, $sellerId, function () use ($listing, $buyerId, $sellerId): self {
            $current = Listing::query()->findOrFail($listing->getKey());
            abort_unless((int) $current->user_id === $sellerId && ! in_array($current->statusValue(), ['paused', 'scheduled'], true), 404);
            $conversation = static::query()->firstOrCreate(['listing_id' => $listing->getKey(), 'buyer_id' => $buyerId], ['seller_id' => $sellerId]);
            abort_unless((int) $conversation->seller_id === $sellerId, 409);

            return $conversation;
        });
    }

    public static function buyerListingConversationId(int $listingId, int $buyerId): ?int
    {
        $value = static::query()
            ->where('listing_id', $listingId)
            ->where('buyer_id', $buyerId)
            ->value('id');

        return is_null($value) ? null : (int) $value;
    }

    public static function detailForBuyerListing(int $listingId, int $buyerId): ?self
    {
        $conversationId = static::buyerListingConversationId($listingId, $buyerId);

        if (! $conversationId) {
            return null;
        }

        $conversation = static::query()
            ->forUser($buyerId)
            ->find($conversationId);

        if (! $conversation) {
            return null;
        }

        $conversation->loadThread();
        $conversation->loadCount([
            'messages as unread_count' => fn (Builder $query) => $query
                ->where('sender_id', '!=', $buyerId)
                ->whereNull('read_at'),
        ]);

        return $conversation;
    }

    public static function listingMapForBuyer(int $buyerId, array $listingIds = []): array
    {
        return static::query()
            ->where('buyer_id', $buyerId)
            ->when($listingIds !== [], fn (Builder $query): Builder => $query->whereIn('listing_id', $listingIds))
            ->pluck('id', 'listing_id')
            ->map(fn ($conversationId): int => (int) $conversationId)
            ->all();
    }

    public static function unreadCountForUser(int $userId): int
    {
        return (int) ConversationMessage::query()
            ->whereHas('conversation', function (Builder $query) use ($userId): void {
                $query->forUser($userId);
            })
            ->where('sender_id', '!=', $userId)
            ->whereNull('read_at')
            ->count();
    }
}
