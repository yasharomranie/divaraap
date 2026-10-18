<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Support\Str;
use Modules\Category\Models\Category;
use Modules\Favorite\App\Support\ListingHistory;
use Modules\Listing\Models\Listing;
use Modules\User\App\Models\User;
use Tests\TestCase;

final class ListingHistoryTest extends TestCase
{
    private function listing(array $data = []): Listing
    {
        return Listing::unguarded(fn () => Listing::create(array_replace(['title' => 'History sample', 'slug' => (string) Str::uuid(), 'description' => 'Viewing history test', 'status' => 'active', 'price' => '5000', 'currency' => 'IRR', 'user_id' => User::where('email', 'a@a.com')->firstOrFail()->id, 'category_id' => Category::firstOrFail()->id], $data)));
    }

    public function test_actual_detail_visits_record_unique_recent_order_without_extra_view_counts(): void
    {
        $first = $this->listing(['title' => 'First viewed']);
        $second = $this->listing(['title' => 'Second viewed']);
        $this->get(route('listings.show', $first))->assertOk();
        $this->get(route('listings.show', $second))->assertOk();
        $this->get(route('listings.show', $first))->assertOk();
        $response = $this->get(route('history.index'))->assertOk();
        $this->assertSame([$first->id, $second->id], $response->viewData('listings')->pluck('id')->all());
        $this->assertSame(2, (int) $first->fresh()->view_count);
        $this->assertSame(1, (int) $second->fresh()->view_count);
        $this->assertSame(2, count(session('listing_history')));
        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
        $response->assertHeader('X-Robots-Tag', 'noindex, nofollow');
    }

    public function test_pause_keeps_previous_entries_clear_does_not_resume_and_resume_records_again(): void
    {
        $first = $this->listing();
        $second = $this->listing();
        $this->get(route('listings.show', $first));
        $this->post(route('history.configure'), ['enabled' => 0])->assertRedirect(route('history.index'));
        $this->get(route('listings.show', $second));
        $this->get(route('history.index'))->assertViewHas('enabled', false)->assertViewHas('listings', fn ($items) => $items->pluck('id')->all() === [$first->id]);
        $this->delete(route('history.clear'))->assertRedirect(route('history.index'));
        $this->get(route('history.index'))->assertViewHas('enabled', false)->assertViewHas('listings', fn ($items) => $items->isEmpty());
        $this->post(route('history.configure'), ['enabled' => 1]);
        $this->get(route('listings.show', $second));
        $this->get(route('history.index'))->assertViewHas('listings', fn ($items) => $items->pluck('id')->all() === [$second->id]);
        $this->post(route('history.configure'), ['enabled' => 'invalid'])->assertSessionHasErrors('enabled');
    }

    public function test_inactive_expired_deleted_and_own_listings_are_excluded(): void
    {
        $history = app(ListingHistory::class);
        foreach (['pending', 'sold', 'expired', 'rejected'] as $status) {
            $history->record($this->listing(['status' => $status]));
        }
        $history->record($this->listing(['expires_at' => now()->subDay()]));
        $this->assertSame([], session('listing_history'));
        $live = $this->listing();
        $history->record($live);
        $live->forceFill(['status' => 'pending'])->save();
        $this->get(route('history.index'))->assertViewHas('listings', fn ($items) => $items->isEmpty());
        $live->forceFill(['status' => 'active'])->save();
        $history->record($live);
        $live->delete();
        $this->get(route('history.index'))->assertViewHas('listings', fn ($items) => $items->isEmpty());
        $owner = User::where('email', 'a@a.com')->firstOrFail();
        $own = $this->listing();
        $this->actingAs($owner)->get(route('listings.show', $own))->assertOk();
        $this->assertSame([], session('listing_history'));
        $this->assertSame(0, (int) $own->fresh()->view_count);
    }

    public function test_account_switch_discards_previous_history_and_sessions_start_empty(): void
    {
        $listing = $this->listing();
        $this->get(route('listings.show', $listing));
        $user = User::factory()->create();
        $this->actingAs($user)->get(route('history.index'))->assertViewHas('listings', fn ($items) => $items->isEmpty());
        $this->get(route('listings.show', $listing));
        $this->actingAs(User::factory()->create())->get(route('history.index'))->assertViewHas('listings', fn ($items) => $items->isEmpty());
        session()->flush();
        $this->get(route('history.index'))->assertViewHas('listings', fn ($items) => $items->isEmpty());
    }

    public function test_cap_retention_and_pagination_keep_latest_fifty_without_storing_listing_content(): void
    {
        $history = app(ListingHistory::class);
        $ids = [];
        for ($i = 0; $i < 52; $i++) {
            $listing = $this->listing();
            $history->record($listing);
            $ids[] = $listing->id;
        }
        $response = $this->get(route('history.index'))->assertOk();
        $this->assertSame(50, $response->viewData('listings')->total());
        $this->assertSame(12, $response->viewData('listings')->count());
        $this->assertSame($ids[51], $response->viewData('listings')->first()->id);
        $this->assertNotContains($ids[0], array_keys(session('listing_history')));
        foreach (session('listing_history') as $at) {
            $this->assertIsInt($at);
        }
        $this->get(route('history.index', ['page' => 5]))->assertViewHas('listings', fn ($items) => $items->count() === 2);
        $this->travel(31)->days();
        $this->get(route('history.index'))->assertViewHas('listings', fn ($items) => $items->isEmpty());
        $this->travelBack();
    }

    public function test_remove_is_idempotent_and_history_content_is_escaped(): void
    {
        $listing = $this->listing(['title' => '<script>historyPayload()</script>']);
        app(ListingHistory::class)->record($listing);
        $this->get(route('history.index'))->assertOk()->assertSee($listing->title)->assertDontSee($listing->title, false);
        $this->delete(route('history.remove', $listing->id))->assertRedirect(route('history.index'));
        $this->delete(route('history.remove', $listing->id))->assertRedirect(route('history.index'));
        $this->get(route('history.index'))->assertViewHas('listings', fn ($items) => $items->isEmpty());
        $this->get(route('history.index', ['ids' => [$listing->id]]))->assertViewHas('listings', fn ($items) => $items->isEmpty());
    }
}
