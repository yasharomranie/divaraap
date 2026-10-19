<?php

declare(strict_types=1);

namespace Tests\Feature;

use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Session\ArraySessionHandler;
use Illuminate\Session\Store;
use Illuminate\Support\Str;
use Modules\Admin\App\Settings\OperationsSettings;
use Modules\Category\Models\Category;
use Modules\Conversation\App\Models\Conversation;
use Modules\Favorite\App\Models\FavoriteListing;
use Modules\Listing\Models\Listing;
use Modules\Listing\Models\ListingPerformanceEvent;
use Modules\Offer\Models\Offer;
use Modules\User\App\Models\User;
use Tests\TestCase;

final class ListingPerformanceTest extends TestCase
{
    private function user(string $email = 'a@a.com'): User
    {
        return User::where('email', $email)->firstOrFail();
    }

    private function listing(array $data = []): Listing
    {
        return Listing::unguarded(fn () => Listing::create(array_replace(['title' => 'Performance test listing', 'description' => 'Performance test', 'slug' => (string) Str::uuid(), 'status' => 'active', 'price' => 1000, 'currency' => 'IRR', 'contact_email' => 'seller@example.test', 'user_id' => $this->user()->id, 'category_id' => Category::where('name', 'Phones')->firstOrFail()->id], $data)));
    }

    private function guest(): Request
    {
        $request = Request::create('/listings/1');
        $session = new Store('performance-test', new ArraySessionHandler(120));
        $session->start();
        $request->setLaravelSession($session);

        return $request;
    }

    public function test_performance_is_owner_only_private_and_does_not_increment_views(): void
    {
        $listing = $this->listing(['view_count' => 42]);
        $url = route('panel.listings.performance', $listing);
        $this->get($url)->assertRedirect(route('login'));
        $this->actingAs($this->user('b@b.com'))->get($url)->assertForbidden();
        $this->actingAs($this->user())->withSession(['locale' => 'fa'])->get($url)->assertOk()
            ->assertHeader('Cache-Control', 'no-store, private')->assertHeader('X-Robots-Tag', 'noindex, nofollow')
            ->assertSee('عملکرد آگهی')->assertSee('شمارندهٔ قدیمی')->assertViewHas('favorites', 0);
        $this->assertSame(42, (int) $listing->fresh()->view_count);
        $listing->delete();
        $this->get($url)->assertNotFound();
    }

    public function test_daily_events_deduplicate_each_browser_and_roll_over_in_app_timezone(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-10-19 21:00:00', 'UTC'));
        config(['app.timezone' => 'Asia/Tehran']);
        $listing = $this->listing();
        $first = $this->guest();
        ListingPerformanceEvent::record($listing, 'view', $first);
        ListingPerformanceEvent::record($listing, 'view', $first);
        ListingPerformanceEvent::record($listing, 'view', $this->guest());
        $events = ListingPerformanceEvent::where('listing_id', $listing->id)->get();
        $this->assertCount(2, $events);
        $this->assertSame('2026-10-20', $events[0]->event_day->toDateString());
        $this->assertNotSame($first->session()->getId(), $events[0]->visitor_hash);
        $this->assertSame(64, strlen($events[0]->visitor_hash));
        $oldHash = $events[0]->visitor_hash;
        $this->travelTo(CarbonImmutable::parse('2026-10-20 21:00:00', 'UTC'));
        ListingPerformanceEvent::record($listing, 'view', $first);
        $this->assertSame(3, ListingPerformanceEvent::where('listing_id', $listing->id)->count());
        $this->assertNotSame($oldHash, ListingPerformanceEvent::where('listing_id', $listing->id)->latest('id')->firstOrFail()->visitor_hash);
    }

    public function test_public_view_and_authorized_contact_are_recorded_without_owner_inflation(): void
    {
        $listing = $this->listing();
        $this->get(route('listings.contact', $listing))->assertRedirect(route('login'));
        $this->actingAs($this->user())->get(route('listings.show', $listing))->assertOk();
        $this->get(route('listings.contact', $listing))->assertForbidden();
        $this->assertSame(0, ListingPerformanceEvent::where('listing_id', $listing->id)->count());
        $this->actingAs($this->user('b@b.com'));
        for ($i = 0; $i < 2; $i++) {
            $this->get(route('listings.show', $listing))->assertOk();
            $this->get(route('listings.contact', $listing))->assertOk()->assertJsonPath('email', 'seller@example.test')->assertHeader('Cache-Control', 'no-store, private');
        }
        $this->assertSame(1, ListingPerformanceEvent::where('listing_id', $listing->id)->where('kind', 'view')->count());
        $this->assertSame(1, ListingPerformanceEvent::where('listing_id', $listing->id)->where('kind', 'contact')->count());
        $this->assertSame(2, (int) $listing->fresh()->view_count);
    }

    public function test_invalid_nonpublic_and_disabled_collection_are_not_recorded(): void
    {
        $listing = $this->listing();
        $request = $this->guest();
        ListingPerformanceEvent::record($listing, 'invalid', $request);
        foreach (['sold', 'pending', 'expired', 'rejected'] as $status) {
            ListingPerformanceEvent::record($this->listing(['status' => $status]), 'view', $request);
        }
        ListingPerformanceEvent::record($this->listing(['expires_at' => now()->subSecond()]), 'view', $request);
        ListingPerformanceEvent::record($this->listing(['user_id' => null]), 'view', $request);
        $settings = app(OperationsSettings::class);
        $settings->telemetry_enabled = false;
        ListingPerformanceEvent::record($listing, 'view', $request);
        $this->assertSame(0, ListingPerformanceEvent::count());
        $this->actingAs($this->user())->get(route('panel.listings.performance', $listing))->assertOk()->assertViewHas('telemetryEnabled', false);
    }

    public function test_report_filters_owner_period_deleted_events_and_marks_missing_history(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-10-19 12:00:00', 'UTC'));
        $listing = $this->listing();
        foreach ([['2026-10-19', 'view', $listing->user_id], ['2026-10-18', 'contact', $listing->user_id], ['2026-09-01', 'view', $listing->user_id], ['2026-10-19', 'view', $this->user('b@b.com')->id]] as [$day, $kind, $owner]) {
            ListingPerformanceEvent::create(['listing_id' => $listing->id, 'owner_id' => $owner, 'event_day' => $day, 'kind' => $kind, 'visitor_hash' => hash('sha256', (string) Str::uuid())]);
        }
        $this->actingAs($this->user());
        $response = $this->get(route('panel.listings.performance', ['listing' => $listing, 'days' => 7]))->assertOk();
        $report = $response->viewData('report');
        $this->assertCount(7, $report['days']);
        $this->assertSame(1, $report['views']);
        $this->assertSame(1, $report['contacts']);
        $this->assertNull($report['days'][0]['view']);
        $this->get(route('panel.listings.performance', ['listing' => $listing, 'days' => 90]))->assertOk()->assertViewHas('report', fn ($value) => $value['views'] === 2);
        ListingPerformanceEvent::where('listing_id', $listing->id)->where('kind', 'contact')->delete();
        $this->get(route('panel.listings.performance', $listing))->assertOk()->assertViewHas('report', fn ($value) => $value['contacts'] === 0);
    }

    public function test_conversation_offer_and_save_metrics_have_independent_scopes(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-10-19 12:00:00', 'UTC'));
        $listing = $this->listing();
        $buyer = $this->user('b@b.com');
        $data = ['listing_id' => $listing->id, 'seller_id' => $listing->user_id, 'buyer_id' => $buyer->id];
        Conversation::create($data);
        (new Conversation(array_replace($data, ['buyer_id' => $this->user('c@c.com')->id])))->forceFill(['created_at' => now()->subMonths(4)])->save();
        Offer::create($data + ['amount' => 1000, 'currency' => 'IRR', 'status' => 'pending']);
        Offer::create(array_replace($data, ['seller_id' => $buyer->id, 'amount' => 1000, 'currency' => 'IRR', 'status' => 'pending']));
        FavoriteListing::rememberFor($buyer, $listing->id);
        $this->actingAs($this->user())->get(route('panel.listings.performance', $listing))->assertOk()
            ->assertViewHas('metrics', fn ($value) => $value['conversations'] === 1 && $value['offers'] === 1)->assertViewHas('favorites', 1);
        FavoriteListing::where('listing_id', $listing->id)->delete();
        $this->get(route('panel.listings.performance', $listing))->assertOk()->assertViewHas('favorites', 0);
    }

    public function test_invalid_periods_are_rejected_and_valid_periods_work(): void
    {
        $listing = $this->listing();
        $this->actingAs($this->user());
        foreach ([['days' => 'bad'], ['days' => 365], ['days' => ['7']]] as $params) {
            $this->getJson(route('panel.listings.performance', ['listing' => $listing] + $params))->assertUnprocessable();
        }
        foreach ([7, 30, 90] as $days) {
            $this->get(route('panel.listings.performance', ['listing' => $listing, 'days' => $days]))->assertOk()->assertViewHas('days', $days);
        }
    }
}
