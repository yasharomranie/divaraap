<?php

declare(strict_types=1);

namespace Modules\Offer\Models;

use App\Support\ListingDirectory;
use App\Support\Money;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Conversation\App\Models\Conversation;
use Modules\Conversation\App\Models\ConversationBlock;
use Modules\Listing\Models\Listing;
use Modules\Notification\Models\UserNotification;
use Modules\User\App\Models\User;

class Offer extends Model
{
    use SoftDeletes;

    public const STATUS_PENDING = 'pending';

    public const STATUS_ACCEPTED = 'accepted';

    public const STATUS_DECLINED = 'declined';

    public const STATUS_WITHDRAWN = 'withdrawn';

    public static function performanceCount(int $listingId, int $sellerId, CarbonImmutable $start, CarbonImmutable $end): int
    {
        return static::query()->where('listing_id', $listingId)->where('seller_id', $sellerId)
            ->whereBetween('created_at', [$start, $end])->count();
    }

    protected $fillable = [
        'listing_id',
        'buyer_id',
        'seller_id',
        'amount',
        'currency',
        'message',
        'status',
        'responded_at',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'responded_at' => 'datetime',
    ];

    public static function statuses(): array
    {
        return [
            self::STATUS_PENDING,
            self::STATUS_ACCEPTED,
            self::STATUS_DECLINED,
            self::STATUS_WITHDRAWN,
        ];
    }

    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    public function scopeForSeller(Builder $query, int $sellerId): Builder
    {
        return $query->where('seller_id', $sellerId);
    }

    public function scopeForBuyer(Builder $query, int $buyerId): Builder
    {
        return $query->where('buyer_id', $buyerId);
    }

    public function scopeForListing(Builder $query, int $listingId): Builder
    {
        return $query->where('listing_id', $listingId);
    }

    public function scopeWithStatus(Builder $query, string $status): Builder
    {
        return in_array($status, self::statuses(), true)
            ? $query->where('status', $status)
            : $query;
    }

    public static function place(int $listingId, int $buyerId, int $sellerId, string|float $amount, string $currency, ?string $message): self
    {
        return ConversationBlock::communicate($buyerId, $sellerId, function () use ($listingId, $buyerId, $sellerId, $amount, $message): self {
            $listing = Listing::query()->lockForUpdate()->findOrFail($listingId);
            abort_unless((int) $listing->user_id === $sellerId && $listing->canPauseDisplay(), 422);
            $currency = (string) $listing->currency;
            if (! Money::valid($amount, $currency) || Money::decimal($amount) === '0.00') {
                throw ValidationException::withMessages(['amount' => __('offer::chat.invalid_amount')]);
            }
            if ($message !== null && mb_strlen($message) > 500) {
                throw ValidationException::withMessages(['message' => __('validation.max.string', ['attribute' => __('offer::messages.offer_message'), 'max' => 500])]);
            }
            static::query()->forListing($listingId)->forBuyer($buyerId)->forSeller($sellerId)->pending()
                ->update(['status' => self::STATUS_WITHDRAWN, 'responded_at' => now()]);
            $offer = static::query()->create([
                'listing_id' => $listingId, 'buyer_id' => $buyerId, 'seller_id' => $sellerId,
                'amount' => Money::decimal($amount), 'currency' => $currency,
                'message' => $message, 'status' => self::STATUS_PENDING,
            ]);
            UserNotification::publish($sellerId, UserNotification::TYPE_OFFER,
                __('offer::messages.notification_received_title'),
                __('offer::messages.notification_received_body', ['amount' => $offer->amountLabel(), 'listing' => $listing->title]),
                $offer->conversationUrl() ?? route('panel.offers.index'));

            return $offer;
        });
    }

    public static function inConversation(Conversation $conversation, int $viewerId): Collection
    {
        abort_unless($conversation->hasParticipant($viewerId), 403);

        return static::query()->forListing((int) $conversation->listing_id)
            ->forBuyer((int) $conversation->buyer_id)->forSeller((int) $conversation->seller_id)
            ->latest('id')->limit(3)->get();
    }

    public static function placeInConversation(Conversation $conversation, int $buyerId, string $amount, ?string $message): self
    {
        abort_unless((int) $conversation->buyer_id === $buyerId, 403);
        $fresh = Conversation::query()->findOrFail($conversation->getKey());
        abort_unless((int) $fresh->buyer_id === $buyerId && (int) $fresh->seller_id === (int) $conversation->seller_id && (int) $fresh->listing_id === (int) $conversation->listing_id, 409);

        return static::place((int) $fresh->listing_id, $buyerId, (int) $fresh->seller_id, $amount, '', $message);
    }

    public function matchesConversation(Conversation $conversation): bool
    {
        return (int) $this->listing_id === (int) $conversation->listing_id
            && (int) $this->buyer_id === (int) $conversation->buyer_id
            && (int) $this->seller_id === (int) $conversation->seller_id;
    }

    public function conversationUrl(): ?string
    {
        $id = Conversation::query()->where('listing_id', $this->listing_id)
            ->where('buyer_id', $this->buyer_id)->where('seller_id', $this->seller_id)->value('id');

        return $id ? route('panel.inbox.index', ['conversation' => $id]) : null;
    }

    public function respondFor(int $actorId, string $status): void
    {
        abort_unless(in_array($status, [self::STATUS_ACCEPTED, self::STATUS_DECLINED, self::STATUS_WITHDRAWN], true), 422);
        $withdraw = $status === self::STATUS_WITHDRAWN;
        abort_unless($withdraw ? $this->belongsToBuyer($actorId) : $this->belongsToSeller($actorId), 403);
        $change = function () use ($actorId, $status, $withdraw): void {
            $listing = $status === self::STATUS_ACCEPTED ? Listing::query()->lockForUpdate()->find($this->listing_id) : null;
            $locked = static::query()->lockForUpdate()->findOrFail($this->getKey());
            abort_unless($withdraw ? $locked->belongsToBuyer($actorId) : $locked->belongsToSeller($actorId), 403);
            abort_unless((int) $locked->buyer_id === (int) $this->buyer_id && (int) $locked->seller_id === (int) $this->seller_id && (int) $locked->listing_id === (int) $this->listing_id, 409);
            abort_unless($locked->isPending(), 409, __('offer::chat.stale'));
            if ($status === self::STATUS_ACCEPTED) {
                abort_unless($listing && $listing->canPauseDisplay()
                    && (int) $listing->user_id === (int) $locked->seller_id
                    && (string) $listing->currency === (string) $locked->currency, 409, __('offer::chat.unavailable'));
            }
            $locked->forceFill(['status' => $status, 'responded_at' => now()])->save();
            $this->setRawAttributes($locked->getAttributes())->syncOriginal();
            if (! $withdraw) {
                $key = $status === self::STATUS_ACCEPTED ? 'accepted' : 'declined';
                UserNotification::publish($locked->buyerId(), UserNotification::TYPE_OFFER,
                    __('offer::messages.notification_'.$key.'_title'),
                    __('offer::messages.notification_'.$key.'_body', ['listing' => ListingDirectory::titleFor($locked->listingId())]),
                    $locked->conversationUrl() ?? route('panel.offers.index', ['direction' => 'sent']));
            }
        };
        if ($withdraw) {
            DB::transaction(function () use ($actorId, $change): void {
                $actor = User::query()->lockForUpdate()->findOrFail($actorId);
                abort_unless((string) $actor->status === 'active', 403);
                $change();
            });
        } else {
            ConversationBlock::communicate($actorId, $this->buyerId(), $change);
        }
    }

    public static function receivedBySeller(int $sellerId, string $status, int $perPage = 12): LengthAwarePaginator
    {
        return static::query()
            ->forSeller($sellerId)
            ->withStatus($status)
            ->latest('id')
            ->paginate($perPage)
            ->withQueryString();
    }

    public static function sentByBuyer(int $buyerId, int $perPage = 12): LengthAwarePaginator
    {
        return static::query()
            ->forBuyer($buyerId)
            ->latest('id')
            ->paginate($perPage)
            ->withQueryString();
    }

    public static function pendingCountForSeller(int $sellerId): int
    {
        return (int) static::query()->forSeller($sellerId)->pending()->count();
    }

    public static function statusCountsForSeller(int $sellerId): array
    {
        $counts = static::query()
            ->forSeller($sellerId)
            ->selectRaw('status, COUNT(*) as aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status');

        return [
            'all' => (int) $counts->sum(),
            self::STATUS_PENDING => (int) ($counts[self::STATUS_PENDING] ?? 0),
            self::STATUS_ACCEPTED => (int) ($counts[self::STATUS_ACCEPTED] ?? 0),
            self::STATUS_DECLINED => (int) ($counts[self::STATUS_DECLINED] ?? 0),
        ];
    }

    public static function highestPendingForListing(int $listingId): ?self
    {
        return static::query()
            ->forListing($listingId)
            ->pending()
            ->orderByDesc('amount')
            ->first();
    }

    public static function listingIdsWithOffersForSeller(int $sellerId): array
    {
        return static::query()
            ->forSeller($sellerId)
            ->pluck('listing_id')
            ->map(static fn (mixed $value): int => (int) $value)
            ->unique()
            ->values()
            ->all();
    }

    public function accept(): void
    {
        $this->respondFor((int) $this->seller_id, self::STATUS_ACCEPTED);
    }

    public function decline(): void
    {
        $this->respondFor((int) $this->seller_id, self::STATUS_DECLINED);
    }

    public function withdraw(): void
    {
        $this->respondFor((int) $this->buyer_id, self::STATUS_WITHDRAWN);
    }

    public function isPending(): bool
    {
        return $this->getAttribute('status') === self::STATUS_PENDING;
    }

    public function belongsToSeller(int $sellerId): bool
    {
        return (int) $this->getAttribute('seller_id') === $sellerId;
    }

    public function belongsToBuyer(int $buyerId): bool
    {
        return (int) $this->getAttribute('buyer_id') === $buyerId;
    }

    public function listingId(): int
    {
        return (int) $this->getAttribute('listing_id');
    }

    public function buyerId(): int
    {
        return (int) $this->getAttribute('buyer_id');
    }

    public function amountLabel(): string
    {
        return Money::format((string) $this->getAttribute('amount'), (string) $this->getAttribute('currency'));
    }

    public function statusTone(): string
    {
        return match ((string) $this->getAttribute('status')) {
            self::STATUS_ACCEPTED => 'positive',
            self::STATUS_DECLINED => 'critical',
            self::STATUS_WITHDRAWN => 'default',
            default => 'caution',
        };
    }
}
