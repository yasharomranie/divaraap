<?php

declare(strict_types=1);

namespace Modules\Listing\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Validation\ValidationException;
use Modules\Conversation\App\Models\ConversationBlock;
use Modules\User\App\Models\User;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

final class WantedResponse extends Model
{
    use LogsActivity;
    use SoftDeletes;

    protected $guarded = ['id'];

    private bool $writing = false;

    protected static function booted(): void
    {
        self::saving(fn (self $response) => abort_unless($response->writing, 403));
        self::deleting(fn () => abort(403));
    }

    public static function submit(User $seller, int $requestId, int $listingId, int $revision): self
    {
        WantedRequest::actor($seller);
        $request = WantedRequest::query()->visible()->findOrFail($requestId);

        return ConversationBlock::communicate((int) $seller->id, (int) $request->user_id, function () use ($seller, $requestId, $listingId, $revision): self {
            $request = WantedRequest::query()->visible()->lockForUpdate()->findOrFail($requestId);
            abort_unless((int) $request->revision === $revision, 409);
            $listing = Listing::wantedCandidates($request->category_id, $request->city_id, (int) $seller->id)->whereKey($listingId)->lockForUpdate()->firstOrFail();
            $existing = self::withTrashed()->where('wanted_request_id', $requestId)->where('revision', $revision)->where('seller_id', $seller->id)->lockForUpdate()->first();
            if ($existing) {
                if ((int) $existing->listing_id !== (int) $listing->id || $existing->trashed()) {
                    throw ValidationException::withMessages(['listing_id' => __('listing::wanted.already_responded')]);
                }

                return $existing;
            }
            $response = new self;
            $response->writing = true;
            $response->fill(['wanted_request_id' => $requestId, 'revision' => $revision, 'seller_id' => $seller->id, 'listing_id' => $listing->id])->save();
            $response->writing = false;

            return $response;
        });
    }

    public static function forOwner(WantedRequest $request, User $owner)
    {
        abort_unless((int) $request->user_id === (int) $owner->id, 404);
        WantedRequest::actor($owner);

        return self::query()->where('wanted_request_id', $request->id)->where('revision', $request->revision)->latest('id')->paginate(12);
    }

    public static function forSeller(WantedRequest $request, User $seller): ?self
    {
        return self::query()->where('wanted_request_id', $request->id)->where('revision', $request->revision)->where('seller_id', $seller->id)->first();
    }

    public static function listingCards(WantedRequest $request, $responses): array
    {
        $rows = $responses->getCollection();
        $listings = Listing::wantedCandidates($request->category_id, $request->city_id)->whereKey($rows->pluck('listing_id'))->pluck('user_id', 'id');
        $ids = $rows->filter(fn (self $response): bool => isset($listings[$response->listing_id]) && (int) $listings[$response->listing_id] === (int) $response->seller_id)->pluck('listing_id')->all();

        $cards = Listing::advertisingCollectionItems($ids);
        $result = [];
        foreach ($rows as $response) {
            if (isset($cards[$response->listing_id], $listings[$response->listing_id]) && (int) $listings[$response->listing_id] === (int) $response->seller_id) {
                $result[$response->id] = $cards[$response->listing_id];
            }
        }

        return $result;
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logOnly(['wanted_request_id', 'revision', 'seller_id', 'listing_id'])->logOnlyDirty()->dontLogEmptyChanges();
    }
}
