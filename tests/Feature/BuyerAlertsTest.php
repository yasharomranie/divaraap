<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Support\Str;
use Modules\Category\Models\Category;
use Modules\Favorite\App\Models\BuyerAlert;
use Modules\Favorite\App\Models\BuyerPublication;
use Modules\Favorite\App\Models\FavoriteSearch;
use Modules\Listing\Models\Listing;
use Modules\Location\Models\City;
use Modules\Notification\Models\UserNotification;
use Modules\User\App\Models\User;
use Tests\TestCase;

final class BuyerAlertsTest extends TestCase
{
    private function listing(User $seller, array $data = []): Listing
    {
        return Listing::unguarded(fn () => Listing::create(array_replace(['title' => 'Alert phone', 'description' => 'Phone details', 'slug' => Str::uuid(), 'price' => '1000.00', 'currency' => 'IRR', 'user_id' => $seller->id, 'status' => 'active', 'category_id' => Category::firstOrFail()->id], $data)));
    }

    private function alertCount(User $user): int
    {
        return UserNotification::where('user_id', $user->id)->where('type', UserNotification::TYPE_SAVED_SEARCH)->count();
    }

    public function test_search_is_opt_in_future_publications_only_and_deduplicated_across_overlapping_searches(): void
    {
        $buyer = User::factory()->create();
        $seller = User::factory()->create();
        $old = $this->listing($seller);
        $search = FavoriteSearch::storeForUser($buyer, ['search' => 'Alert phone']);
        BuyerAlert::refreshFor($buyer);
        $this->assertSame(0, $this->alertCount($buyer));
        BuyerAlert::configure($buyer, 'search', $search->id, true);
        $second = FavoriteSearch::storeForUser($buyer, ['search' => 'phone']);
        BuyerAlert::configure($buyer, 'search', $second->id, true);
        $listing = $this->listing($seller);
        $this->listing($seller, ['title' => 'Unrelated']);
        $this->listing($buyer);
        $this->listing($seller, ['expires_at' => now()->subDay()]);
        $pending = $this->listing($seller, ['status' => 'pending']);
        BuyerAlert::refreshFor($buyer);
        BuyerAlert::refreshFor($buyer);
        $this->assertSame(1, $this->alertCount($buyer));
        $this->assertSame(route('listings.show', $listing), UserNotification::where('user_id', $buyer->id)->sole()->action_url);
        $pending->forceFill(['status' => 'active'])->save();
        BuyerAlert::refreshFor($buyer);
        $this->assertSame(2, $this->alertCount($buyer));
        $listing->update(['description' => 'Edited text']);
        BuyerAlert::refreshFor($buyer);
        $this->assertSame(2, $this->alertCount($buyer));
        $this->assertSame(1, BuyerPublication::where('listing_id', $old->id)->count());
    }

    public function test_seller_alert_stops_on_unfollow_and_is_not_shared_with_other_buyers(): void
    {
        $buyer = User::factory()->create();
        $seller = User::factory()->create();
        $buyer->toggleFavoriteSeller($seller);
        BuyerAlert::configure($buyer, 'seller', $seller->id, true);
        $this->listing($seller);
        $this->actingAs($buyer)->get(route('panel.notifications.index'))->assertOk();
        $this->assertSame(1, $this->alertCount($buyer));
        $buyer->toggleFavoriteSeller($seller);
        $this->listing($seller);
        BuyerAlert::refreshFor($buyer);
        $this->assertSame(1, $this->alertCount($buyer));
        $this->assertFalse(BuyerAlert::where('user_id', $buyer->id)->sole()->enabled);
        $other = User::factory()->create();
        $this->actingAs($other)->get(route('favorites.alerts'))->assertOk()->assertDontSee('Alert phone');
        $this->assertSame(0, $this->alertCount($other));
    }

    public function test_price_uses_decimal_low_watermark_and_currency_changes_reset_it(): void
    {
        $buyer = User::factory()->create();
        $listing = $this->listing(User::factory()->create());
        $buyer->rememberListing($listing);
        BuyerAlert::configure($buyer, 'price', $listing->id, true);
        $listing->update(['price' => '999.99']);
        BuyerAlert::refreshFor($buyer);
        BuyerAlert::refreshFor($buyer);
        $this->assertSame(1, $this->alertCount($buyer));
        $listing->update(['price' => '1200']);
        BuyerAlert::refreshFor($buyer);
        $listing->update(['price' => '1100']);
        BuyerAlert::refreshFor($buyer);
        $this->assertSame(1, $this->alertCount($buyer));
        $listing->update(['price' => '990']);
        BuyerAlert::refreshFor($buyer);
        $this->assertSame(2, $this->alertCount($buyer));
        $listing->update(['price' => '10', 'currency' => 'USD']);
        BuyerAlert::refreshFor($buyer);
        $this->assertSame(2, $this->alertCount($buyer));
        $listing->update(['price' => '9']);
        BuyerAlert::refreshFor($buyer);
        $this->assertSame(3, $this->alertCount($buyer));
        $buyer->toggleFavoriteListing($listing);
        $listing->update(['price' => '8']);
        BuyerAlert::refreshFor($buyer);
        $this->assertSame(3, $this->alertCount($buyer));
    }

    public function test_controls_reauthorize_and_preserve_cursor_on_repeated_enable(): void
    {
        $buyer = User::factory()->create();
        $seller = User::factory()->create();
        $search = FavoriteSearch::storeForUser($buyer, ['search' => 'Alert']);
        $data = ['kind' => 'search', 'source_id' => $search->id, 'enabled' => 1];
        $this->post(route('favorites.alerts.configure'), $data)->assertRedirect(route('login'));
        $this->actingAs($seller)->post(route('favorites.alerts.configure'), $data)->assertNotFound();
        $this->actingAs($buyer)->post(route('favorites.alerts.configure'), $data)->assertRedirect();
        $this->listing($seller);
        $this->post(route('favorites.alerts.configure'), $data)->assertRedirect();
        BuyerAlert::refreshFor($buyer);
        $this->assertSame(1, $this->alertCount($buyer));
        $this->post(route('favorites.alerts.configure'), ['kind' => 'invalid', 'source_id' => 1, 'enabled' => 1])->assertSessionHasErrors('kind');
        $buyer->forceFill(['status' => 'suspended'])->save();
        $this->post(route('favorites.alerts.configure'), $data)->assertForbidden();
    }

    public function test_disabled_and_deleted_searches_do_not_notify_and_reenable_starts_now(): void
    {
        $buyer = User::factory()->create();
        $seller = User::factory()->create();
        $search = FavoriteSearch::storeForUser($buyer, ['search' => 'Alert']);
        BuyerAlert::configure($buyer, 'search', $search->id, true);
        BuyerAlert::configure($buyer, 'search', $search->id, false);
        $this->listing($seller);
        BuyerAlert::configure($buyer, 'search', $search->id, true);
        BuyerAlert::refreshFor($buyer);
        $this->assertSame(0, $this->alertCount($buyer));
        $search->delete();
        $this->listing($seller);
        BuyerAlert::refreshFor($buyer);
        $this->assertFalse(BuyerAlert::where('user_id', $buyer->id)->sole()->enabled);
        $this->assertSame(0, $this->alertCount($buyer));
    }

    public function test_batch_progress_does_not_drop_matching_publications_and_inactive_listings_stay_private(): void
    {
        $buyer = User::factory()->create();
        $seller = User::factory()->create();
        $search = FavoriteSearch::storeForUser($buyer, ['search' => 'Alert']);
        BuyerAlert::configure($buyer, 'search', $search->id, true);
        for ($i = 0; $i < 52; $i++) {
            $listing = $this->listing($seller);
        }
        $listing->forceFill(['status' => 'sold'])->save();
        BuyerAlert::refreshFor($buyer);
        $this->assertSame(50, $this->alertCount($buyer));
        BuyerAlert::refreshFor($buyer);
        $this->assertSame(51, $this->alertCount($buyer));
        BuyerAlert::refreshFor($buyer);
        $this->assertSame(51, $this->alertCount($buyer));
    }

    public function test_twenty_alert_limit_allows_disable_and_existing_idempotent_enable(): void
    {
        $buyer = User::factory()->create();
        $searches = [];
        for ($i = 0; $i < 21; $i++) {
            $searches[] = FavoriteSearch::storeForUser($buyer, ['search' => 'unique '.$i]);
        }
        foreach (array_slice($searches, 0, 20) as $search) {
            BuyerAlert::configure($buyer, 'search', $search->id, true);
        }
        $this->actingAs($buyer)->post(route('favorites.alerts.configure'), ['kind' => 'search', 'source_id' => $searches[20]->id, 'enabled' => 1])->assertSessionHasErrors('enabled');
        BuyerAlert::configure($buyer, 'search', $searches[0]->id, true);
        BuyerAlert::configure($buyer, 'search', $searches[0]->id, false);
        BuyerAlert::configure($buyer, 'search', $searches[20]->id, true);
        $this->assertSame(20, BuyerAlert::where('user_id', $buyer->id)->where('enabled', true)->count());
    }

    public function test_category_filters_match_browse_descendants_and_suspended_buyers_are_not_notified(): void
    {
        $buyer = User::factory()->create();
        $seller = User::factory()->create();
        $category = Category::whereNotNull('parent_id')->firstOrFail();
        $search = FavoriteSearch::storeForUser($buyer, ['category' => $category->parent_id]);
        BuyerAlert::configure($buyer, 'search', $search->id, true);
        $this->listing($seller, ['category_id' => $category->id]);
        BuyerAlert::refreshFor($buyer);
        $this->assertSame(1, $this->alertCount($buyer));
        $buyer->forceFill(['status' => 'suspended'])->save();
        $this->listing($seller, ['category_id' => $category->id]);
        BuyerAlert::refreshFor($buyer);
        $this->assertSame(1, $this->alertCount($buyer));
    }

    public function test_saved_search_preserves_location_seller_and_price_and_matches_only_the_same_filters(): void
    {
        $buyer = User::factory()->create();
        $seller = User::factory()->create();
        $city = City::with('country')->firstOrFail();
        $category = Category::firstOrFail();
        $data = ['search' => 'Alert', 'category' => $category->id, 'country' => $city->country_id, 'city' => $city->id, 'user' => $seller->id, 'min_price' => '100', 'max_price' => '200'];
        $this->actingAs($buyer)->post(route('favorites.searches.store'), $data)->assertRedirect()->assertSessionHasNoErrors();
        $search = FavoriteSearch::where('user_id', $buyer->id)->sole();
        $this->assertSame('100.00', $search->filters['min_price']);
        $this->assertSame($city->id, $search->filters['city']);
        BuyerAlert::configure($buyer, 'search', $search->id, true);
        $attributes = ['title' => 'Alert filtered', 'category_id' => $category->id, 'city' => $city->name, 'country' => $city->country->name, 'price' => '150'];
        $match = $this->listing($seller, $attributes);
        $this->listing($seller, [...$attributes, 'price' => '250']);
        $this->listing($seller, [...$attributes, 'city' => 'Another city']);
        $this->listing(User::factory()->create(), $attributes);
        BuyerAlert::refreshFor($buyer);
        $this->assertSame(1, $this->alertCount($buyer));
        $this->assertSame(route('listings.show', $match), UserNotification::where('user_id', $buyer->id)->sole()->action_url);
        $this->get(route('listings.index', $data))->assertOk()->assertViewHas('isCurrentSearchSaved', true);
    }

    public function test_saved_search_page_uses_real_search_term_and_lists_alert_controls(): void
    {
        $buyer = User::factory()->create();
        FavoriteSearch::storeForUser($buyer, ['search' => 'گوشی انتخابی']);
        $this->actingAs($buyer)->withSession(['locale' => 'fa'])->get(route('favorites.index', ['tab' => 'searches']))->assertOk()->assertSee('گوشی انتخابی')->assertSee('هشدارهای من')->assertSee('name="source_id"', false)->assertSee('search=', false);
        $this->get(route('favorites.alerts'))->assertOk()->assertSee('پیامک');
    }
}
