<?php

declare(strict_types=1);

namespace Modules\Review\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Conversation\App\Models\Conversation;
use Modules\Listing\Models\Listing;
use Modules\User\App\Models\User;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

class Review extends Model
{
    use LogsActivity;
    use SoftDeletes;

    public const MIN_RATING = 1;

    public const MAX_RATING = 5;

    protected static function booted(): void
    {
        static::saving(function (self $review): void {
            if ($review->exists && $review->isDirty(['seller_id', 'author_id', 'listing_id'])) {
                $review->interaction_verified_at = null;
                $review->interaction_conversation_id = null;
            }
            if ($review->exists && $review->isDirty('is_approved') && ! $review->isDirty('reviewed_at')) {
                $review->reviewed_at = now();
            }
        });
    }

    public static function submitFor(User $author, int $sellerId, ?int $listingId, int $rating, ?string $title, ?string $body): self
    {
        return DB::transaction(function () use ($author, $sellerId, $listingId, $rating, $title, $body): self {
            $users = User::query()->whereKey([$author->getKey(), $sellerId])->orderBy('id')->lockForUpdate()->get()->keyBy('id');
            $writer = $users->get($author->getKey());
            $seller = $users->get($sellerId);
            abort_unless($writer && (string) $writer->status === 'active', 403);
            abort_unless($seller && (string) $seller->status === 'active', 404);
            if ($writer->getKey() === $seller->getKey()) {
                throw ValidationException::withMessages(['rating' => __('review::messages.cannot_review_self')]);
            }
            if ($listingId !== null && ! Listing::query()->whereKey($listingId)->ownedByUser($sellerId)->exists()) {
                throw ValidationException::withMessages(['listing_id' => __('review::quality.invalid_listing')]);
            }
            if ($rating < self::MIN_RATING || $rating > self::MAX_RATING) {
                throw ValidationException::withMessages(['rating' => __('review::quality.invalid_rating')]);
            }
            $duplicate = static::query()->writtenBy((int) $writer->getKey())->forSeller($sellerId)
                ->when($listingId === null, fn (Builder $query) => $query->whereNull('listing_id'), fn (Builder $query) => $query->forListing($listingId))->exists();
            if ($duplicate) {
                throw ValidationException::withMessages(['rating' => __('review::quality.duplicate')]);
            }
            $evidence = Conversation::reviewEvidenceFor((int) $writer->getKey(), $sellerId, $listingId);
            $review = static::query()->make([
                'seller_id' => $sellerId, 'author_id' => $writer->getKey(), 'listing_id' => $listingId,
                'rating' => $rating, 'title' => $title, 'body' => $body, 'is_approved' => false,
            ]);
            $review->forceFill(['interaction_conversation_id' => $evidence, 'interaction_verified_at' => $evidence ? now() : null])->save();

            return $review;
        });
    }

    public static function writtenPageFor(User $user): LengthAwarePaginator
    {
        return static::query()->writtenBy((int) $user->getKey())->latest('id')->paginate(10)->withQueryString();
    }

    public function hasInteractionEvidence(): bool
    {
        return $this->interaction_verified_at !== null && $this->interaction_conversation_id !== null;
    }

    public function moderationLabel(): string
    {
        return __('review::quality.'.($this->is_approved ? 'approved' : ($this->reviewed_at ? 'rejected' : 'pending')));
    }

    public static function moderationQueue(): Collection
    {
        return static::query()->where('is_approved', false)->whereNull('reviewed_at')->oldest()->limit(20)->get(['id', 'title', 'body', 'created_at']);
    }

    public function moderate(bool $approve, ?string $note = null): void
    {
        $this->forceFill(['is_approved' => $approve, 'reviewed_at' => now()])->save();
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logOnly(['is_approved', 'reviewed_at', 'rating'])->logOnlyDirty()->dontLogEmptyChanges();
    }

    protected $fillable = [
        'seller_id',
        'author_id',
        'listing_id',
        'rating',
        'title',
        'body',
        'is_approved',
    ];

    protected $casts = [
        'rating' => 'integer',
        'reviewed_at' => 'datetime',
        'interaction_verified_at' => 'datetime',
        'is_approved' => 'boolean',
    ];

    public function scopeApproved(Builder $query): Builder
    {
        return $query->where('is_approved', true);
    }

    public function scopeForSeller(Builder $query, int $sellerId): Builder
    {
        return $query->where('seller_id', $sellerId);
    }

    public function scopeForListing(Builder $query, int $listingId): Builder
    {
        return $query->where('listing_id', $listingId);
    }

    public function scopeWrittenBy(Builder $query, int $authorId): Builder
    {
        return $query->where('author_id', $authorId);
    }

    public static function summaryForSeller(int $sellerId): array
    {
        $row = static::query()
            ->approved()
            ->forSeller($sellerId)
            ->selectRaw('COUNT(*) as total, COALESCE(AVG(rating), 0) as average')
            ->first();

        $total = (int) ($row?->getAttribute('total') ?? 0);

        return [
            'total' => $total,
            'average' => $total > 0 ? round((float) ($row?->getAttribute('average') ?? 0), 1) : 0.0,
        ];
    }

    public static function summariesForSellers(array $sellerIds): array
    {
        if ($sellerIds === []) {
            return [];
        }

        return static::query()
            ->approved()
            ->whereIn('seller_id', $sellerIds)
            ->selectRaw('seller_id, COUNT(*) as total, COALESCE(AVG(rating), 0) as average')
            ->groupBy('seller_id')
            ->get()
            ->mapWithKeys(fn (self $row): array => [
                (int) $row->getAttribute('seller_id') => [
                    'total' => (int) $row->getAttribute('total'),
                    'average' => round((float) $row->getAttribute('average'), 1),
                ],
            ])
            ->all();
    }

    public static function distributionForSeller(int $sellerId): array
    {
        $counts = static::query()
            ->approved()
            ->forSeller($sellerId)
            ->selectRaw('rating, COUNT(*) as total')
            ->groupBy('rating')
            ->pluck('total', 'rating');

        $distribution = [];

        for ($score = self::MAX_RATING; $score >= self::MIN_RATING; $score--) {
            $distribution[$score] = (int) ($counts[$score] ?? 0);
        }

        return $distribution;
    }

    public static function paginateForSeller(int $sellerId, int $perPage = 10): LengthAwarePaginator
    {
        return static::query()
            ->approved()
            ->forSeller($sellerId)
            ->latest('id')
            ->paginate($perPage)
            ->withQueryString();
    }

    public static function latestForSeller(int $sellerId, int $limit = 5): Collection
    {
        return static::query()
            ->approved()
            ->forSeller($sellerId)
            ->latest('id')
            ->limit($limit)
            ->get();
    }

    public static function receivedBySeller(int $sellerId, int $perPage = 10): LengthAwarePaginator
    {
        return static::query()
            ->forSeller($sellerId)
            ->latest('id')
            ->paginate($perPage)
            ->withQueryString();
    }

    public static function authorHasReviewed(int $authorId, int $listingId): bool
    {
        return static::query()
            ->writtenBy($authorId)
            ->forListing($listingId)
            ->exists();
    }

    public static function record(int $sellerId, int $authorId, ?int $listingId, int $rating, ?string $title, ?string $body): self
    {
        $review = static::query()->make([
            'seller_id' => $sellerId,
            'author_id' => $authorId,
            'listing_id' => $listingId,
            'rating' => max(self::MIN_RATING, min(self::MAX_RATING, $rating)),
            'title' => $title,
            'body' => $body,
            'is_approved' => true,
        ]);

        $review->save();

        return $review;
    }

    public static function pendingCount(): int
    {
        return (int) static::query()->where('is_approved', false)->whereNull('reviewed_at')->count();
    }

    public function approve(): void
    {
        $this->moderate(true);
    }

    public function reject(): void
    {
        $this->moderate(false);
    }

    public function authorId(): int
    {
        return (int) $this->getAttribute('author_id');
    }

    public function sellerId(): int
    {
        return (int) $this->getAttribute('seller_id');
    }

    public function listingId(): ?int
    {
        $value = $this->getAttribute('listing_id');

        return $value === null ? null : (int) $value;
    }

    public function ratingValue(): int
    {
        return (int) $this->getAttribute('rating');
    }
}
