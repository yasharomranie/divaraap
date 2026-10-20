<?php

declare(strict_types=1);

namespace Modules\User\App\Http\Controllers;

use App\Support\UserDirectory;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\Conversation\App\Models\Conversation;
use Modules\Listing\Models\Listing;
use Modules\Review\Models\Review;
use Modules\User\App\Models\Profile;
use Modules\User\App\Models\User;

class SellerController extends Controller
{
    public function show(Request $request, int $seller): View
    {
        $data = $request->validate(['search' => ['nullable', 'string', 'max:120']]);
        $search = trim($data['search'] ?? '');
        $profile = User::query()->where('status', 'active')->whereKey($seller)->first(['id', 'name', 'created_at']);

        abort_if($profile === null, 404);

        $sellerId = (int) $profile->getKey();
        $reviews = Review::paginateForSeller($sellerId);
        $viewerId = $request->user() === null ? null : (int) $request->user()->getKey();

        return view('user::sellers.show', [
            'seller' => $profile,
            'shop' => Profile::shopFor($profile),
            'search' => $search,
            'verification' => Profile::publicVerification($sellerId),
            'responseSummary' => Conversation::sellerResponseSummary($sellerId),
            'listings' => Listing::publicSellerListings($sellerId, $search),
            'listingCount' => Listing::publicSellerCount($sellerId),
            'summary' => Review::summaryForSeller($sellerId),
            'distribution' => Review::distributionForSeller($sellerId),
            'reviews' => $reviews,
            'reviewAuthors' => UserDirectory::resolve(
                $reviews->getCollection()->map(static fn (Review $review): int => $review->authorId())->all()
            ),
            'canReview' => $viewerId !== null && $viewerId !== $sellerId,
            'isSelf' => $viewerId === $sellerId,
        ]);
    }
}
