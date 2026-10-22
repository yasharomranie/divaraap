<?php

declare(strict_types=1);

namespace Modules\Favorite\App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Support\PersianSearch;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Validation\ValidationException;
use Modules\Category\Models\Category;
use Modules\Conversation\App\Models\Conversation;
use Modules\Favorite\App\Models\BuyerAlert;
use Modules\Favorite\App\Models\FavoriteCollection;
use Modules\Favorite\App\Models\FavoriteSearch;
use Modules\Listing\Models\Listing;
use Modules\User\App\Models\User;
use Modules\User\App\Support\AuthRedirector;

class FavoriteController extends Controller
{
    public function __construct(private AuthRedirector $redirector) {}

    public function index(Request $request)
    {
        $activeTab = (string) $request->string('tab', 'listings');
        if (! in_array($activeTab, ['listings', 'searches', 'sellers'], true)) {
            $activeTab = 'listings';
        }

        $statusFilter = (string) $request->string('status', 'all');
        if (! in_array($statusFilter, ['all', 'active'], true)) {
            $statusFilter = 'all';
        }

        $selectedCategoryId = $request->integer('category');
        if ($selectedCategoryId <= 0) {
            $selectedCategoryId = null;
        }

        $user = $request->user();
        $requiresLogin = ! $user;
        $collections = $user ? FavoriteCollection::optionsFor($user->id) : [];
        $collectionFilter = $request->input('collection');
        if ($collectionFilter !== 'none') {
            $collectionFilter = $request->integer('collection') ?: null;
            if ($collectionFilter && $user) {
                FavoriteCollection::assertOwned($user->id, $collectionFilter);
            }
        }

        $categories = Category::filterOptions();

        $favoriteListings = $this->emptyPaginator();
        $favoriteSearches = $this->emptyPaginator();
        $favoriteSellers = $this->emptyPaginator();
        $buyerConversationListingMap = [];

        if ($user && $activeTab === 'listings') {
            $favoriteListings = $user->favoriteListingsPage($statusFilter, $selectedCategoryId, 10, $collectionFilter);

            if ($favoriteListings->isNotEmpty()) {
                $buyerConversationListingMap = Conversation::listingMapForBuyer(
                    (int) $user->getKey(),
                    $favoriteListings->pluck('id')->all(),
                );
            }
        }

        if ($user && $activeTab === 'searches') {
            $favoriteSearches = $user->favoriteSearchesPage();
        }

        if ($user && $activeTab === 'sellers') {
            $favoriteSellers = $user->favoriteSellersPage();
        }

        $alertPreferences = $user ? BuyerAlert::preferencesFor($user->id) : [];

        return response()->view('favorite::index', [
            'alertPreferences' => $alertPreferences,
            'collections' => $collections,
            'collectionFilter' => $collectionFilter,
            'activeTab' => $activeTab,
            'statusFilter' => $statusFilter,
            'selectedCategoryId' => $selectedCategoryId,
            'categories' => $categories,
            'favoriteListings' => $favoriteListings,
            'favoriteSearches' => $favoriteSearches,
            'favoriteSellers' => $favoriteSellers,
            'buyerConversationListingMap' => $buyerConversationListingMap,
            'requiresLogin' => $requiresLogin,
        ])->header('Cache-Control', 'private, no-store');
    }

    public function toggleListing(Request $request, Listing $listing)
    {
        $isNowFavorite = $request->user()->toggleFavoriteListing($listing);

        return $this->redirectBack($request)->with('success', $isNowFavorite ? __('favorite::messages.listing_added') : __('favorite::messages.listing_removed'));
    }

    public function toggleSeller(Request $request, User $seller)
    {
        $user = $request->user();

        if ((int) $user->getKey() === (int) $seller->getKey()) {
            return $this->redirectBack($request)->with('error', __('favorite::messages.own_account'));
        }

        $isNowFavorite = $user->toggleFavoriteSeller($seller);

        return $this->redirectBack($request)->with('success', $isNowFavorite ? __('favorite::messages.seller_added') : __('favorite::messages.seller_removed'));
    }

    public function storeSearch(Request $request)
    {
        $request->merge(['category' => $request->input('category', $request->input('category_id'))]);
        $data = $request->validate([
            'search' => ['nullable', 'string', 'max:120'],
            'category' => ['nullable', 'integer', 'exists:categories,id'],
            'country' => ['nullable', 'integer', 'exists:countries,id'],
            'city' => ['nullable', 'integer', 'exists:cities,id'],
            'user' => ['nullable', 'integer', 'exists:users,id'],
            'min_price' => ['nullable', 'string', 'max:24'],
            'max_price' => ['nullable', 'string', 'max:24'],
        ]);

        if (PersianSearch::terms((string) ($data['search'] ?? '')) === null) {
            throw ValidationException::withMessages(['search' => __('listing::search.limit')]);
        }
        $filters = FavoriteSearch::browseFilters($data);

        if ($filters === []) {
            return back()->with('error', __('favorite::messages.select_filter'));
        }

        $favoriteSearch = FavoriteSearch::storeForUser($request->user(), $filters);

        if (! $favoriteSearch->wasRecentlyCreated) {
            return back()->with('success', __('favorite::messages.already_saved'));
        }

        return back()->with('success', __('favorite::messages.search_saved'));
    }

    public function destroySearch(Request $request, FavoriteSearch $favoriteSearch)
    {
        if ((int) $favoriteSearch->user_id !== (int) $request->user()->getKey()) {
            abort(403);
        }

        BuyerAlert::disableSource($request->user()->id, 'search', $favoriteSearch->id);
        $favoriteSearch->delete();

        return back()->with('success', __('favorite::messages.search_removed'));
    }

    public function alerts(Request $request)
    {
        BuyerAlert::refreshFor($request->user());

        return response()->view('favorite::alerts', ['alerts' => BuyerAlert::pageFor($request->user()->id)])->header('Cache-Control', 'private, no-store')->header('X-Robots-Tag', 'noindex, nofollow');
    }

    public function configureAlert(Request $request)
    {
        abort_unless((string) $request->user()->status === 'active', 403);
        $data = $request->validate(['kind' => ['required', 'in:search,seller,price,rare'], 'source_id' => ['required', 'integer', 'min:1'], 'enabled' => ['required', 'boolean']]);
        BuyerAlert::configure($request->user(), $data['kind'], (int) $data['source_id'], (bool) $data['enabled']);

        return back()->with('success', __('favorite::alerts.saved'));
    }

    private function emptyPaginator(): LengthAwarePaginator
    {
        return new LengthAwarePaginator([], 0, 10, 1, [
            'path' => request()->url(),
            'query' => request()->query(),
        ]);
    }

    private function redirectBack(Request $request): RedirectResponse
    {
        $target = $this->redirector->sanitize((string) $request->input('redirect_to', ''));

        if ($target !== null) {
            return redirect()->to($target);
        }

        return back();
    }
}
