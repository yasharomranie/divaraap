<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Modules\Category\Models\Category;
use Modules\Conversation\App\Models\ConversationBlock;
use Modules\Favorite\App\Models\BuyerAlert;
use Modules\Favorite\App\Models\FavoriteSearch;
use Modules\Favorite\App\Models\RareItemWatch;
use Modules\Listing\Models\Listing;
use Modules\Location\Models\City;
use Modules\Notification\Models\UserNotification;
use Modules\User\App\Models\User;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

final class RareItemWatchTest extends TestCase
{
    private User $buyer;

    private User $seller;

    private int $category;

    protected function setUp(): void
    {
        parent::setUp();
        $this->buyer = User::factory()->create(['status' => 'active']);
        $this->seller = User::factory()->create(['status' => 'active']);
        $this->buyer->syncRoles([]);
        $this->seller->syncRoles([]);
        $this->category = Category::where('name', 'Phones')->firstOrFail()->id;
    }

    private function input(array $data = []): array
    {
        return array_replace(['search' => 'گوشی کلاسیک ۱۲۳', 'category' => $this->category], $data);
    }

    private function listing(array $data = []): Listing
    {
        return Listing::unguarded(fn () => Listing::create(array_replace(['title' => 'گوشی کلاسیک 123', 'description' => 'گوشی سالم آماده فروش', 'slug' => (string) Str::uuid(), 'status' => 'active', 'user_id' => $this->seller->id, 'category_id' => $this->category, 'price' => 1000, 'currency' => 'IRR', 'expires_at' => now()->addDays(7)], $data)));
    }

    private function countAlerts(): int
    {
        return UserNotification::where('user_id', $this->buyer->id)->where('type', UserNotification::TYPE_SAVED_SEARCH)->count();
    }

    private function invalid(callable $action): void
    {
        try {
            $action();
            $this->fail('Expected validation failure.');
        } catch (ValidationException) {
            $this->assertTrue(true);
        }
    }

    private function httpFailure(int $status, callable $action): void
    {
        try {
            $action();
            $this->fail('Expected HTTP failure.');
        } catch (ModelNotFoundException) {
            $this->assertSame(404, $status);
        } catch (HttpException $exception) {
            $this->assertSame($status, $exception->getStatusCode());
        }
    }

    public function test_creation_is_opt_in_private_immutable_and_canonical_retries_preserve_opt_out(): void
    {
        $old = $this->listing();
        $watch = RareItemWatch::createFor($this->buyer, $this->input(['user_id' => $this->seller->id, 'expires_at' => now()->addYear(), 'enabled' => false]));
        $this->assertSame($this->buyer->id, $watch->user_id);
        $this->assertSame(30, (int) round(now()->diffInDays($watch->expires_at)));
        $this->assertTrue(BuyerAlert::where('source_id', $watch->id)->where('kind', 'rare')->sole()->enabled);
        BuyerAlert::refreshFor($this->buyer);
        $this->assertSame(0, $this->countAlerts());
        $watch->changeFor($this->buyer, 'pause');
        $same = RareItemWatch::createFor($this->buyer, $this->input(['search' => 'گوشي كلاسيك 123']));
        $this->assertSame($watch->id, $same->id);
        $this->assertFalse(BuyerAlert::where('source_id', $watch->id)->where('kind', 'rare')->sole()->enabled);
        $this->httpFailure(403, fn () => $watch->update(['filters' => ['search' => 'Other']]));
        $this->httpFailure(403, fn () => $watch->delete());
        $this->assertNotNull($old->id);
    }

    public function test_actual_publications_notify_once_across_overlapping_rare_watches_and_normal_alerts_stay_independent(): void
    {
        RareItemWatch::createFor($this->buyer, $this->input());
        RareItemWatch::createFor($this->buyer, $this->input(['search' => 'کلاسیک']));
        $search = FavoriteSearch::storeForUser($this->buyer, ['search' => 'کلاسیک']);
        BuyerAlert::configure($this->buyer, 'search', $search->id, true);
        $listing = $this->listing();
        BuyerAlert::refreshFor($this->buyer);
        BuyerAlert::refreshFor($this->buyer);
        $this->assertSame(2, $this->countAlerts());
        $this->assertSame(1, UserNotification::where('user_id', $this->buyer->id)->where('title', __('favorite::rare.matched'))->count());
        $this->assertSame(2, UserNotification::where('user_id', $this->buyer->id)->where('action_url', route('listings.show', $listing))->count());
        $listing->update(['description' => 'Changed text']);
        BuyerAlert::refreshFor($this->buyer);
        $this->assertSame(2, $this->countAlerts());
    }

    public function test_budget_currency_category_city_account_block_and_publication_status_are_enforced(): void
    {
        $city = City::active()->with('country')->firstOrFail();
        RareItemWatch::createFor($this->buyer, $this->input(['city' => $city->id, 'max_price' => 1000]));
        $location = ['city' => $city->name, 'country' => $city->country->name];
        $match = $this->listing($location);
        foreach ([['price' => 1001], ['price' => null], ['currency' => 'USD'], ['city' => 'Other location'], ['category_id' => Category::whereKeyNot($this->category)->firstOrFail()->id], ['status' => 'pending'], ['status' => 'paused'], ['expires_at' => now()->subDay()], ['user_id' => $this->buyer->id]] as $data) {
            $this->listing(array_replace($location, $data));
        }
        $inactive = User::factory()->create(['status' => 'suspended']);
        $this->listing(array_replace($location, ['user_id' => $inactive->id]));
        $blocked = User::factory()->create(['status' => 'active']);
        ConversationBlock::setFor($this->buyer, $blocked->id, true);
        $this->listing(array_replace($location, ['user_id' => $blocked->id]));
        BuyerAlert::refreshFor($this->buyer);
        $this->assertSame(1, $this->countAlerts());
        $this->assertSame(route('listings.show', $match), UserNotification::where('user_id', $this->buyer->id)->sole()->action_url);
    }

    public function test_pause_resume_skips_paused_backlog_and_preserves_prior_receipts(): void
    {
        $watch = RareItemWatch::createFor($this->buyer, $this->input());
        $first = $this->listing();
        BuyerAlert::refreshFor($this->buyer);
        $watch->changeFor($this->buyer, 'pause');
        $this->listing();
        BuyerAlert::refreshFor($this->buyer);
        $this->assertSame(1, $this->countAlerts());
        $watch->changeFor($this->buyer, 'resume');
        BuyerAlert::refreshFor($this->buyer);
        $this->assertSame(1, $this->countAlerts());
        $this->listing();
        BuyerAlert::refreshFor($this->buyer);
        $this->assertSame(2, $this->countAlerts());
        $first->update(['description' => 'Still the same publication']);
        BuyerAlert::refreshFor($this->buyer);
        $this->assertSame(2, $this->countAlerts());
    }

    public function test_expiry_stops_processing_and_explicit_renewal_starts_a_new_fixed_window(): void
    {
        $watch = RareItemWatch::createFor($this->buyer, $this->input());
        $this->travel(31)->days();
        $this->listing();
        BuyerAlert::refreshFor($this->buyer);
        $this->assertSame(0, $this->countAlerts());
        $this->assertFalse(BuyerAlert::where('kind', 'rare')->where('source_id', $watch->id)->sole()->enabled);
        $this->httpFailure(404, fn () => $watch->changeFor($this->buyer, 'resume'));
        $watch->changeFor($this->buyer, 'renew');
        $this->assertSame(30, (int) round(now()->diffInDays($watch->expires_at)));
        BuyerAlert::refreshFor($this->buyer);
        $this->assertSame(0, $this->countAlerts());
        $this->listing();
        BuyerAlert::refreshFor($this->buyer);
        $this->assertSame(1, $this->countAlerts());
        $this->httpFailure(409, fn () => $watch->changeFor($this->buyer, 'renew'));
    }

    public function test_remove_soft_deletes_and_active_identity_can_be_created_again(): void
    {
        $watch = RareItemWatch::createFor($this->buyer, $this->input());
        $watch->changeFor($this->buyer, 'remove');
        $this->assertTrue($watch->fresh()->trashed());
        $this->assertNull(RareItemWatch::availableFor($this->buyer->id, $watch->id));
        $new = RareItemWatch::createFor($this->buyer, $this->input());
        $this->assertNotSame($watch->id, $new->id);
        $this->assertSame(2, RareItemWatch::withTrashed()->where('user_id', $this->buyer->id)->count());
        $this->assertSame(1, RareItemWatch::where('user_id', $this->buyer->id)->count());
    }

    public function test_shared_twenty_enabled_limit_is_atomic_and_expired_rare_slots_are_released(): void
    {
        for ($i = 0; $i < 20; $i++) {
            $source = FavoriteSearch::storeForUser($this->buyer, ['search' => 'query-'.$i]);
            BuyerAlert::configure($this->buyer, 'search', $source->id, true);
        }
        $this->invalid(fn () => RareItemWatch::createFor($this->buyer, $this->input()));
        $this->assertSame(0, RareItemWatch::where('user_id', $this->buyer->id)->count());
        $alert = BuyerAlert::where('user_id', $this->buyer->id)->firstOrFail();
        BuyerAlert::configure($this->buyer, $alert->kind, $alert->source_id, false);
        $watch = RareItemWatch::createFor($this->buyer, $this->input());
        $this->assertSame(20, BuyerAlert::where('user_id', $this->buyer->id)->where('enabled', true)->count());
        $this->travel(31)->days();
        BuyerAlert::configure($this->buyer, $alert->kind, $alert->source_id, true);
        $this->assertFalse(BuyerAlert::where('kind', 'rare')->where('source_id', $watch->id)->sole()->enabled);
    }

    public function test_criteria_validation_and_inactive_category_or_city_prevent_delivery(): void
    {
        foreach ([['search' => ' '], ['search' => 'a b c d e f g h i'], ['search' => str_repeat('a', 121)], ['category' => 99999999], ['city' => 99999999], ['max_price' => '1.5'], ['max_price' => -1]] as $data) {
            $this->invalid(fn () => RareItemWatch::createFor($this->buyer, $this->input($data)));
        }
        $city = City::active()->firstOrFail();
        $watch = RareItemWatch::createFor($this->buyer, $this->input(['city' => $city->id]));
        $city->update(['is_active' => false]);
        $this->assertNull(RareItemWatch::availableFor($this->buyer->id, $watch->id));
        BuyerAlert::refreshFor($this->buyer);
        $this->assertFalse(BuyerAlert::where('kind', 'rare')->where('source_id', $watch->id)->sole()->enabled);
        $city->update(['is_active' => true]);
        Category::findOrFail($this->category)->update(['is_active' => false]);
        $this->httpFailure(404, fn () => $watch->changeFor($this->buyer, 'resume'));
    }

    public function test_catalog_cap_counts_paused_watches_and_removal_releases_a_slot(): void
    {
        for ($i = 0; $i < 20; $i++) {
            $watch = RareItemWatch::createFor($this->buyer, $this->input(['search' => 'Rare item '.$i]));
            $watch->changeFor($this->buyer, 'pause');
        }
        $this->invalid(fn () => RareItemWatch::createFor($this->buyer, $this->input()));
        $watch->changeFor($this->buyer, 'remove');
        $this->assertNotNull(RareItemWatch::createFor($this->buyer, $this->input())->id);
    }

    public function test_http_private_scoping_escape_existing_alert_screen_and_suspension(): void
    {
        $this->actingAs($this->buyer)->post(route('rare-watches.store'), $this->input(['search' => '<script>alert(1)</script>']))->assertRedirect(route('rare-watches.index'));
        $watch = RareItemWatch::where('user_id', $this->buyer->id)->sole();
        $this->get(route('rare-watches.index'))->assertOk()->assertHeader('Cache-Control', 'no-store, private')->assertHeader('X-Robots-Tag', 'noindex, nofollow')->assertSee(e($watch->filters['search']), false)->assertDontSee('<script>alert(1)</script>', false);
        $this->get(route('favorites.alerts'))->assertOk()->assertSee(__('favorite::alerts.rare_toggle'));
        $this->actingAs($this->seller)->get(route('rare-watches.index'))->assertDontSee(e($watch->filters['search']), false);
        $this->post(route('rare-watches.change', $watch), ['action' => 'remove'])->assertNotFound();
        $this->buyer->update(['status' => 'suspended']);
        $this->actingAs($this->buyer)->get(route('rare-watches.index'))->assertForbidden();
        $this->post(route('rare-watches.store'), $this->input())->assertForbidden();
    }

    public function test_batches_are_bounded_and_continue_without_duplicate_notifications(): void
    {
        RareItemWatch::createFor($this->buyer, $this->input());
        for ($i = 0; $i < 51; $i++) {
            $this->listing();
        }
        BuyerAlert::refreshFor($this->buyer);
        $this->assertSame(50, $this->countAlerts());
        BuyerAlert::refreshFor($this->buyer);
        $this->assertSame(51, $this->countAlerts());
        BuyerAlert::refreshFor($this->buyer);
        $this->assertSame(51, $this->countAlerts());
    }
}
