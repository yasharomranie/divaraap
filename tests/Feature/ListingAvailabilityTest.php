<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Support\Str;
use Modules\Category\Models\Category;
use Modules\Conversation\App\Models\Conversation;
use Modules\Listing\Models\Listing;
use Modules\Listing\States\ListingStatus;
use Modules\User\App\Models\User;
use Tests\TestCase;

final class ListingAvailabilityTest extends TestCase
{
    private function user(): User
    {
        return User::where('email', 'a@a.com')->firstOrFail();
    }

    private function listing(array $overrides = []): Listing
    {
        return Listing::unguarded(fn () => Listing::create(array_replace(['title' => 'آگهی آزمون توقف موقت', 'description' => 'توضیح آگهی', 'slug' => (string) Str::uuid(), 'status' => 'active', 'user_id' => $this->user()->id, 'category_id' => Category::where('name', 'Phones')->firstOrFail()->id, 'price' => 1000, 'currency' => 'IRR', 'contact_email' => 'seller@example.test', 'expires_at' => now()->addDays(7)], $overrides)));
    }

    public function test_pause_and_resume_preserve_content_expiry_counts_and_approval(): void
    {
        $listing = $this->listing(['view_count' => 17])->fresh();
        $original = $listing->getAttributes();
        $this->actingAs($this->user())->post(route('panel.listings.pause', $listing))->assertRedirect();
        $listing->refresh();
        $this->assertSame('paused', $listing->statusValue());
        $this->assertArrayHasKey('paused', ListingStatus::options());
        $this->assertFalse(Listing::active()->whereKey($listing->id)->exists());
        $this->assertSame(1, Listing::panelStatusCountsForUser($this->user()->id)['paused']);
        $this->get(route('panel.listings.index', ['status' => 'paused']))->assertOk()->assertSee($listing->title)->assertSee(route('panel.listings.resume', $listing), false);
        $this->post(route('panel.listings.resume', $listing))->assertRedirect();
        $listing->refresh();
        $this->assertSame('active', $listing->statusValue());
        foreach (['title', 'description', 'expires_at', 'view_count', 'price'] as $key) {
            $this->assertSame($original[$key], $listing->getAttributes()[$key]);
        }
    }

    public function test_paused_listing_is_hidden_from_public_feeds_search_map_related_and_direct_access(): void
    {
        $listing = $this->listing(['latitude' => 35.7, 'longitude' => 51.4]);
        $listing->changeDisplayFor($this->user(), true);
        $other = $this->listing();
        $this->assertFalse(Listing::publicFeed()->whereKey($listing->id)->exists());
        $this->assertSame([], Listing::mapResults(Listing::query()->whereKey($listing->id), null)['points']);
        $this->assertFalse($other->relatedSuggestions(8)->contains('id', $listing->id));
        $this->get(route('listings.show', $listing))->assertNotFound();
        $this->get(route('listings.index', ['search' => $listing->title]))->assertOk()->assertDontSee('href="'.route('listings.show', $listing).'"', false);
        $this->actingAs($this->user())->get(route('listings.show', $listing))->assertOk()->assertHeader('Cache-Control', 'no-store, private');
        $buyer = User::whereKeyNot($this->user()->id)->firstOrFail();
        $this->actingAs($buyer)->get(route('listings.show', $listing))->assertNotFound();
        $this->get(route('listings.contact', $listing))->assertForbidden();
        $this->post(route('conversations.start', $listing), ['message' => 'سلام'])->assertNotFound();
        $this->post(route('offers.store', $listing->id), ['amount' => 1000])->assertNotFound();
    }

    public function test_pending_rejected_sold_and_expired_listings_cannot_bypass_moderation(): void
    {
        $this->actingAs($this->user());
        foreach (['pending', 'rejected', 'sold', 'expired'] as $status) {
            $listing = $this->listing(['status' => $status]);
            $this->post(route('panel.listings.pause', $listing))->assertSessionHasErrors('status');
            $this->post(route('panel.listings.resume', $listing))->assertSessionHasErrors('status');
            $this->assertSame($status, $listing->fresh()->statusValue());
        }
        $pending = $this->listing(['status' => 'pending']);
        $pending->updateFromPanel(['status' => 'paused']);
        $this->assertSame('pending', $pending->statusValue());
    }

    public function test_owner_edit_and_renewal_do_not_accidentally_make_a_paused_listing_public(): void
    {
        $listing = $this->listing();
        $listing->changeDisplayFor($this->user(), true);
        $listing->updateFromPanel(['title' => 'ویرایش در توقف', 'status' => 'active']);
        $this->assertSame('paused', $listing->statusValue());
        $listing->republish();
        $this->assertSame('paused', $listing->statusValue());
        $this->assertTrue($listing->expires_at->isFuture());
        $listing->markAsSold();
        $this->assertSame('sold', $listing->statusValue());
    }

    public function test_resume_requires_unexpired_listing_and_admin_rejection_is_respected(): void
    {
        $listing = $this->listing(['status' => 'paused', 'expires_at' => now()->subMinute()]);
        $this->actingAs($this->user())->post(route('panel.listings.resume', $listing))->assertSessionHasErrors('status');
        $this->assertSame('paused', $listing->fresh()->statusValue());
        $this->post(route('panel.listings.republish', $listing))->assertRedirect();
        $this->assertSame('paused', $listing->fresh()->statusValue());
        $this->post(route('panel.listings.resume', $listing))->assertRedirect();
        $this->assertSame('active', $listing->fresh()->statusValue());
        $listing->forceFill(['status' => 'rejected'])->save();
        $this->post(route('panel.listings.resume', $listing))->assertSessionHasErrors('status');
        $this->assertSame('rejected', $listing->fresh()->statusValue());
    }

    public function test_foreign_guest_and_suspended_accounts_cannot_change_visibility(): void
    {
        $listing = $this->listing();
        $this->post(route('panel.listings.pause', $listing))->assertRedirect();
        $other = User::whereKeyNot($this->user()->id)->firstOrFail();
        $this->actingAs($other)->post(route('panel.listings.pause', $listing))->assertForbidden();
        $this->post('/panel/my-listings/not-a-number/pause')->assertNotFound();
        $this->post('/panel/my-listings/99999999999999999999999/resume')->assertNotFound();
        $user = $this->user();
        $user->forceFill(['status' => 'suspended'])->saveQuietly();
        $this->actingAs($user)->post(route('panel.listings.pause', $listing))->assertForbidden();
        $this->assertSame('active', $listing->fresh()->statusValue());
    }

    public function test_existing_conversation_is_preserved_and_can_continue_while_paused(): void
    {
        $listing = $this->listing();
        $buyer = User::whereKeyNot($this->user()->id)->firstOrFail();
        $conversation = Conversation::openForListingBuyer($listing, $buyer->id);
        $conversation->createMessageFor($buyer->id, 'گفتگوی قبلی');
        $listing->changeDisplayFor($this->user(), true);
        $this->actingAs($buyer)->postJson(route('conversations.messages.send', $conversation), ['message' => 'پیگیری گفتگوی قبلی'])->assertOk();
        $this->assertSame(2, $conversation->messages()->count());
        $this->assertSame('paused', $listing->fresh()->statusValue());
    }

    public function test_paused_orphan_is_not_public_and_repeated_pause_is_idempotent(): void
    {
        $orphan = $this->listing(['user_id' => null, 'status' => 'paused']);
        $this->get(route('listings.show', $orphan))->assertNotFound();
        $listing = $this->listing();
        $this->actingAs($this->user())->post(route('panel.listings.pause', $listing))->assertRedirect();
        $updated = $listing->fresh()->updated_at;
        $this->post(route('panel.listings.pause', $listing))->assertRedirect();
        $this->assertSame('paused', $listing->fresh()->statusValue());
        $this->assertTrue($updated->equalTo($listing->fresh()->updated_at));
    }
}
