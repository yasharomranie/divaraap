<?php

declare(strict_types=1);

namespace Modules\Listing\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Support\Money;
use Illuminate\Http\JsonResponse;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Validator;
use Modules\Admin\Models\ProductEvent;
use Modules\Category\Models\Category;
use Modules\Conversation\App\Models\Conversation;
use Modules\Favorite\App\Models\FavoriteSearch;
use Modules\Favorite\App\Support\ListingHistory;
use Modules\Listing\Models\Listing;
use Modules\Listing\Models\ListingPerformanceEvent;
use Modules\Listing\Support\ListingCustomFieldSchemaBuilder;
use Modules\Listing\Support\ListingMapBounds;
use Modules\Location\Models\Country;
use Modules\Offer\Models\Offer;
use Modules\Report\Models\Report;
use Modules\Review\Models\Review;
use Modules\Theme\Support\ThemeManager;
use Modules\User\App\Models\Profile;

class ListingController extends Controller
{
    public function __construct(private ThemeManager $themes) {}

    public function index()
    {
        if (request()->routeIs('listings.map')) {
            $validator = Validator::make(request()->query(), [
                'search' => ['nullable', 'string'], 'min_price' => ['nullable', 'string'], 'max_price' => ['nullable', 'string'],
                'sort' => ['nullable', 'string'], 'date_filter' => ['nullable', 'string'],
                'category' => ['nullable', 'integer', 'min:1'], 'country' => ['nullable', 'integer', 'min:1'],
                'city' => ['nullable', 'integer', 'min:1'], 'user' => ['nullable', 'integer', 'min:1'],
            ]);
            abort_unless($validator->passes(), 422);
        }
        $searchInput = request('search', '');
        abort_unless(is_string($searchInput) || $searchInput === null, 422);
        $search = trim((string) $searchInput);

        $categoryId = request()->integer('category');
        $categoryId = $categoryId > 0 ? $categoryId : null;

        $countryId = request()->integer('country');
        $countryId = $countryId > 0 ? $countryId : null;

        $cityId = request()->integer('city');
        $cityId = $cityId > 0 ? $cityId : null;

        $sellerUserId = request()->integer('user');
        $sellerUserId = $sellerUserId > 0 ? $sellerUserId : null;

        $minPriceInput = trim((string) request('min_price', ''));
        $maxPriceInput = trim((string) request('max_price', ''));
        $minPrice = Money::valid($minPriceInput, 'IRR') ? Money::decimal($minPriceInput) : null;
        $maxPrice = Money::valid($maxPriceInput, 'IRR') ? Money::decimal($maxPriceInput) : null;

        $dateFilter = (string) request('date_filter', 'all');
        $allowedDateFilters = ['all', 'today', 'week', 'month'];
        if (! in_array($dateFilter, $allowedDateFilters, true)) {
            $dateFilter = 'all';
        }

        $sort = (string) request('sort', 'smart');
        $allowedSorts = ['smart', 'newest', 'oldest', 'price_asc', 'price_desc'];
        if (! in_array($sort, $allowedSorts, true)) {
            $sort = 'smart';
        }

        $locationSelection = Country::browseSelection($countryId, $cityId);
        $countryId = $locationSelection['country_id'];
        $cityId = $locationSelection['city_id'];
        $countries = $locationSelection['countries'];
        $cities = $locationSelection['cities'];
        $selectedCountryName = $locationSelection['selected_country_name'];
        $selectedCityName = $locationSelection['selected_city_name'];

        $listingDirectory = Category::listingDirectory($categoryId);

        $browseFilters = [
            'search' => $search,
            'country' => $selectedCountryName,
            'city' => $selectedCityName,
            'user_id' => $sellerUserId,
            'min_price' => $minPrice,
            'max_price' => $maxPrice,
            'date_filter' => $dateFilter,
        ];

        $allListingsTotal = Listing::query()
            ->active()
            ->forBrowseFilters($browseFilters)
            ->count();

        $listingsQuery = Listing::query()
            ->active()
            ->with('category:id,name')
            ->forBrowseFilters([
                ...$browseFilters,
                'category_ids' => $listingDirectory['filterIds'],
            ])
            ->applyBrowseSort($sort);

        if (request()->routeIs('listings.map')) {
            $bounds = ListingMapBounds::fromQuery(request()->query());
            $results = Listing::mapResults($listingsQuery, $bounds);
            $mapPoints = $results['points'];
            $mapTotal = $results['total'];
            $currentPage = min(10, LengthAwarePaginator::resolveCurrentPage());
            $listings = new LengthAwarePaginator($results['listings']->forPage($currentPage, 20)->values(), count($mapPoints), 20, $currentPage, ['path' => route('listings.map'), 'query' => request()->query()]);
            $categoryOptions = Category::filterOptions();

            return response()->view('listing::map', compact('bounds', 'mapPoints', 'mapTotal', 'listings', 'categoryOptions', 'search', 'categoryId', 'countryId', 'cityId', 'countries', 'cities', 'minPriceInput', 'maxPriceInput', 'dateFilter', 'sort', 'sellerUserId'))->header('X-Robots-Tag', 'noindex, follow');
        }

        $filteredListingsTotal = (clone $listingsQuery)->count();

        if ($filteredListingsTotal === 0 && ($search !== '' || $categoryId || $countryId || $cityId)) {
            ProductEvent::record('search_empty', $search ?: '[filters]', ['category_id' => $categoryId, 'country_id' => $countryId, 'city_id' => $cityId]);
        }

        $listings = $listingsQuery
            ->paginate(16)
            ->withQueryString();

        $categories = $listingDirectory['categories'];
        $selectedCategory = $listingDirectory['selectedCategory'];

        $favoriteListingIds = [];
        $isCurrentSearchSaved = false;
        $conversationListingMap = [];

        if (auth()->check()) {
            $userId = (int) auth()->id();

            $favoriteListingIds = auth()->user()->favoriteListingIds();
            $conversationListingMap = Conversation::listingMapForBuyer($userId);

            $isCurrentSearchSaved = FavoriteSearch::isSavedForUser(auth()->user(), FavoriteSearch::browseFilters([
                'search' => $search, 'category' => $categoryId, 'country' => $countryId, 'city' => $cityId, 'user' => $sellerUserId, 'min_price' => $minPrice, 'max_price' => $maxPrice,
            ]));
        }

        return view($this->themes->view('listing', 'index'), compact(
            'listings',
            'search',
            'categoryId',
            'countryId',
            'cityId',
            'sellerUserId',
            'minPriceInput',
            'maxPriceInput',
            'dateFilter',
            'sort',
            'countries',
            'cities',
            'selectedCategory',
            'categories',
            'favoriteListingIds',
            'isCurrentSearchSaved',
            'conversationListingMap',
            'allListingsTotal',
            'filteredListingsTotal',
        ));
    }

    public function show(Listing $listing)
    {
        abort_if(in_array($listing->statusValue(), ['paused', 'scheduled'], true) && (! auth()->check() || ! $listing->user_id || (int) $listing->user_id !== (int) auth()->id()), 404);
        $listing->trackViewBy(auth()->id());
        ListingPerformanceEvent::record($listing, 'view', request());
        app(ListingHistory::class)->record($listing);

        $listing->loadMissing([
            'user:id,name,email',
            'category:id,name,parent_id,slug',
            'category.parent:id,name,parent_id,slug',
            'category.parent.parent:id,name,parent_id,slug',
            'videos' => fn ($query) => $query->published()->ordered(),
        ]);
        $presentableCustomFields = ListingCustomFieldSchemaBuilder::presentableValues(
            $listing->category_id ? (int) $listing->category_id : null,
            $listing->custom_fields ?? [],
        );
        $gallery = $listing->galleryImageData();
        $listingVideos = $listing->getRelation('videos');
        $relatedListings = $listing->relatedSuggestions(8);
        $themePillCategories = Category::themePills(10);
        $breadcrumbCategories = $listing->category
            ? $listing->category->breadcrumbTrail()
            : collect();

        $isListingFavorited = false;
        $isSellerFavorited = false;
        $detailConversation = null;

        if (auth()->check()) {
            $userId = (int) auth()->id();

            $isListingFavorited = in_array((int) $listing->getKey(), auth()->user()->favoriteListingIds(), true);

            if ($listing->user_id) {
                $isSellerFavorited = auth()->user()
                    ->favoriteSellers()
                    ->whereKey($listing->user_id)
                    ->exists();
            }

            if ($listing->user_id && (int) $listing->user_id !== $userId) {
                $detailConversation = Conversation::detailForBuyerListing(
                    (int) $listing->getKey(),
                    $userId,
                );
            }
        }

        $sellerId = $listing->getAttribute('user_id') === null ? null : (int) $listing->getAttribute('user_id');
        $verification = $sellerId === null ? ['email' => false, 'reviewed' => false, 'reviewed_at' => null] : Profile::publicVerification($sellerId);
        $responseSummary = $sellerId === null ? null : Conversation::sellerResponseSummary($sellerId);
        $sellerReviewSummary = $sellerId === null
            ? ['total' => 0, 'average' => 0.0]
            : Review::summaryForSeller($sellerId);
        $bestOffer = Offer::highestPendingForListing((int) $listing->getKey());
        $reportReasons = Report::reasons();

        $view = view($this->themes->view('listing', 'show'), compact(
            'listing',
            'sellerId',
            'sellerReviewSummary',
            'responseSummary',
            'verification',
            'bestOffer',
            'reportReasons',
            'isListingFavorited',
            'isSellerFavorited',
            'presentableCustomFields',
            'detailConversation',
            'gallery',
            'listingVideos',
            'relatedListings',
            'themePillCategories',
            'breadcrumbCategories',
        ));

        return in_array($listing->statusValue(), ['paused', 'scheduled'], true) ? response($view->render())->header('Cache-Control', 'private, no-store')->header('X-Robots-Tag', 'noindex, nofollow') : $view;
    }

    public function contact(Listing $listing): JsonResponse
    {
        abort_unless($listing->canRevealContactTo(auth()->user()), 403);

        ListingPerformanceEvent::record($listing, 'contact', request());

        return response()->json($listing->contactDetailsFor(auth()->user()))->header('Cache-Control', 'private, no-store');
    }

    public function create()
    {
        if (! auth()->check()) {
            return redirect()->route('login');
        }

        return redirect()->route('panel.listings.create');
    }

    public function store()
    {
        if (! auth()->check()) {
            return redirect()->route('login');
        }

        return redirect()
            ->route('panel.listings.create')
            ->with('success', __('listing::messages.redirected_to_create'));
    }
}
