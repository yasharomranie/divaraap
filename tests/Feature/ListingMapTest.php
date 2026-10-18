<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Support\Str;
use Modules\Category\Models\Category;
use Modules\Listing\Models\Listing;
use Modules\Location\Models\City;
use Modules\User\App\Models\User;
use Tests\TestCase;

final class ListingMapTest extends TestCase
{
    private function listing(array $data = []): Listing
    {
        return Listing::unguarded(fn () => Listing::create(array_replace(['title' => 'Map sample phone', 'description' => 'Public map test', 'slug' => (string) Str::uuid(), 'status' => 'active', 'latitude' => '35.1234567', 'longitude' => '51.1234567', 'price' => '1000', 'currency' => 'IRR', 'user_id' => User::where('email', 'a@a.com')->firstOrFail()->id, 'category_id' => Category::where('name', 'Phones')->firstOrFail()->id], $data)));
    }

    public function test_map_includes_only_eligible_located_public_listings_and_rounds_coordinates(): void
    {
        $first = $this->listing(['contact_phone' => 'secret-phone', 'contact_email' => 'secret@example.test']);
        $zero = $this->listing(['latitude' => 0, 'longitude' => 0]);
        foreach (['pending', 'rejected', 'sold', 'expired'] as $status) {
            $this->listing(['status' => $status]);
        }
        $this->listing(['latitude' => null]);
        $this->listing(['longitude' => null]);
        $this->listing(['latitude' => 89]);
        $this->listing(['longitude' => 181]);
        $this->listing(['expires_at' => now()->subDay()]);
        $deleted = $this->listing();
        $deleted->delete();
        $response = $this->get(route('listings.map', ['search' => 'map sample']))->assertOk();
        $this->assertSame(2, $response->viewData('mapTotal'));
        $points = collect($response->viewData('mapPoints'))->keyBy('id');
        $this->assertSame(35.123, $points[$first->id]['lat']);
        $this->assertSame(51.123, $points[$first->id]['lng']);
        $this->assertSame(0.0, $points[$zero->id]['lat']);
        $response->assertDontSee('35.1234567', false)->assertDontSee('secret-phone')->assertDontSee('secret@example.test');
        $this->assertSame(['id', 'title', 'price', 'lat', 'lng', 'url'], array_keys($points[$first->id]));
    }

    public function test_bounds_filters_and_dateline_crossing_work_without_fabricating_locations(): void
    {
        $inside = $this->listing();
        $this->listing(['latitude' => 36]);
        $bounds = ['south' => 35, 'north' => 35.5, 'west' => 51, 'east' => 51.5, 'search' => 'map sample'];
        $this->get(route('listings.map', $bounds))->assertOk()->assertViewHas('mapPoints', fn ($points) => array_column($points, 'id') === [$inside->id]);
        $east = $this->listing(['longitude' => 179, 'latitude' => 1]);
        $west = $this->listing(['longitude' => -179, 'latitude' => 1]);
        $this->listing(['longitude' => 0, 'latitude' => 1]);
        $response = $this->get(route('listings.map', ['south' => 0, 'north' => 2, 'west' => 170, 'east' => -170, 'search' => 'map sample']))->assertOk();
        $this->assertEqualsCanonicalizing([$east->id, $west->id], array_column($response->viewData('mapPoints'), 'id'));
    }

    public function test_invalid_or_partial_bounds_are_rejected(): void
    {
        $this->get(route('listings.map', ['min_price' => ['invalid']]))->assertStatus(422);
        $this->get(route('listings.map', ['category' => ['invalid']]))->assertStatus(422);
        foreach ([['west' => 1], ['west' => 2, 'east' => 2, 'south' => 0, 'north' => 1], ['west' => -10, 'east' => 10, 'south' => 5, 'north' => 2], ['west' => -181, 'east' => 10, 'south' => 0, 'north' => 1], ['west' => 'NaN', 'east' => 10, 'south' => 0, 'north' => 1], ['west' => 180, 'east' => -180, 'south' => 0, 'north' => 1]] as $bounds) {
            $this->get(route('listings.map', $bounds))->assertStatus(422);
        }
    }

    public function test_same_persian_search_category_city_price_seller_and_date_filters_apply(): void
    {
        $city = City::with('country')->firstOrFail();
        $listing = $this->listing(['title' => 'گوشي مدل ١٢٨', 'city' => $city->name, 'country' => $city->country->name]);
        $this->listing(['title' => 'گوشي مدل ١٢٨', 'city' => $city->name, 'country' => $city->country->name, 'price' => '2000']);
        $this->listing(['title' => 'گوشي مدل ١٢٨', 'city' => 'Other city', 'country' => $city->country->name]);
        $this->listing(['title' => 'گوشي مدل ١٢٨', 'created_at' => now()->subMonth(), 'city' => $city->name, 'country' => $city->country->name]);
        $response = $this->get(route('listings.map', ['search' => '۱۲۸ گوشی', 'category' => $listing->category_id, 'country' => $city->country_id, 'city' => $city->id, 'user' => $listing->user_id, 'max_price' => '1000', 'date_filter' => 'today']))->assertOk();
        $this->assertSame([$listing->id], array_column($response->viewData('mapPoints'), 'id'));
        $this->assertSame(1, $response->viewData('mapTotal'));
    }

    public function test_result_cap_and_list_pagination_are_explicit_and_stable(): void
    {
        for ($i = 0; $i < 203; $i++) {
            $this->listing(['title' => 'Map cap fixture '.$i]);
        }
        $query = ['search' => 'Map cap fixture', 'sort' => 'oldest'];
        $response = $this->get(route('listings.map', $query))->assertOk();
        $this->assertCount(200, $response->viewData('mapPoints'));
        $this->assertSame(203, $response->viewData('mapTotal'));
        $this->assertSame(200, $response->viewData('listings')->total());
        $this->assertCount(20, $response->viewData('listings'));
        $this->get(route('listings.map', [...$query, 'page' => 10]))->assertOk()->assertViewHas('listings', fn ($items) => $items->count() === 20);
    }

    public function test_public_map_escapes_titles_and_keeps_plain_result_links_as_fallback(): void
    {
        $listing = $this->listing(['title' => '</script><script>mapPayload()</script>']);
        $response = $this->get(route('listings.map', ['user' => $listing->user_id]))->assertOk();
        $response->assertSee($listing->title)->assertDontSee($listing->title, false)->assertSee(route('listings.show', $listing->id), false)->assertSee('data-map-points', false)->assertHeader('X-Robots-Tag', 'noindex, follow');
        $this->assertSame(35.1234567, (float) $listing->fresh()->latitude);
        $this->get(route('listings.map', ['south' => 35.1229, 'north' => 35.1231, 'west' => 51.1229, 'east' => 51.1231, 'user' => $listing->user_id]))->assertOk()->assertViewHas('mapPoints', fn ($points) => in_array($listing->id, array_column($points, 'id'), true));
    }

    public function test_owner_can_choose_or_clear_rounded_location_without_changing_other_listing_data(): void
    {
        $owner = User::factory()->create();
        $listing = $this->listing(['user_id' => $owner->id, 'latitude' => null, 'longitude' => null, 'status' => 'pending']);
        $this->actingAs($owner)->get(route('listings.location', $listing))->assertOk();
        $this->post(route('listings.location.save', $listing), ['latitude' => '35.1234567', 'longitude' => '51.1234567', 'status' => 'active', 'price' => 1])->assertRedirect(route('listings.location', $listing));
        $fresh = $listing->fresh();
        $this->assertSame(35.123, (float) $fresh->latitude);
        $this->assertSame(51.123, (float) $fresh->longitude);
        $this->assertSame('pending', $fresh->statusValue());
        $this->assertSame('1000.00', $fresh->price);
        $this->delete(route('listings.location.remove', $listing))->assertRedirect();
        $this->assertNull($listing->fresh()->latitude);
        $this->assertNull($listing->fresh()->longitude);
        $this->assertNotNull(Listing::find($listing->id));
    }

    public function test_location_mutations_require_owner_active_status_and_valid_coordinates(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $listing = $this->listing(['user_id' => $owner->id]);
        $this->get(route('listings.location', $listing))->assertRedirect(route('login'));
        $this->actingAs($other)->get(route('listings.location', $listing))->assertForbidden();
        $this->post(route('listings.location.save', $listing), ['latitude' => 0, 'longitude' => 0])->assertForbidden();
        $this->delete(route('listings.location.remove', $listing))->assertForbidden();
        $this->actingAs($owner)->post(route('listings.location.save', $listing), ['latitude' => 91, 'longitude' => -181])->assertSessionHasErrors(['latitude', 'longitude']);
        $owner->forceFill(['status' => 'suspended'])->save();
        $this->post(route('listings.location.save', $listing), ['latitude' => 0, 'longitude' => 0])->assertForbidden();
        $this->delete(route('listings.location.remove', $listing))->assertForbidden();
    }
}
