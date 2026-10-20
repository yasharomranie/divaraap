<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Support\Str;
use Modules\Category\Models\Category;
use Modules\Conversation\App\Models\Conversation;
use Modules\Conversation\App\Models\ConversationMessage;
use Modules\Listing\Models\Listing;
use Modules\Review\Models\Review;
use Modules\User\App\Models\User;
use Tests\TestCase;

final class ReviewQualityTest extends TestCase
{
    private User $buyer;

    private User $seller;

    private Listing $listing;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(now()->startOfSecond());
        $this->buyer = $this->account();
        $this->seller = $this->account();
        $this->listing = Listing::unguarded(fn () => Listing::create(['title' => 'Review listing', 'description' => 'Fixture', 'slug' => (string) Str::uuid(), 'user_id' => $this->seller->id, 'category_id' => Category::firstOrFail()->id, 'price' => 100, 'currency' => 'IRR', 'status' => 'active']));
    }

    protected function tearDown(): void
    {
        $this->travelBack();
        parent::tearDown();
    }

    private function account(): User
    {
        return User::create(['name' => 'Review fixture', 'email' => Str::uuid().'@example.test', 'password' => 'fixture-password', 'status' => 'active']);
    }

    private function conversation(bool $reply = true): Conversation
    {
        $thread = Conversation::unguarded(fn () => Conversation::create(['listing_id' => $this->listing->id, 'buyer_id' => $this->buyer->id, 'seller_id' => $this->seller->id, 'created_at' => now()->subDay()]));
        foreach ($reply ? [$this->buyer, $this->seller] : [$this->buyer] as $sender) {
            ConversationMessage::unguarded(fn () => ConversationMessage::create(['conversation_id' => $thread->id, 'sender_id' => $sender->id, 'body' => 'PRIVATE CONVERSATION TEXT', 'created_at' => now()->subHour()]));
        }

        return $thread;
    }

    private function submit(?int $listingId = null): Review
    {
        return Review::submitFor($this->buyer, $this->seller->id, $listingId, 5, 'Review title', 'Review body');
    }

    public function test_two_way_evidence_is_derived_but_public_review_waits_for_approval(): void
    {
        $thread = $this->conversation();
        $this->actingAs($this->buyer)->post(route('reviews.store', $this->seller), ['rating' => 5, 'title' => 'Verified interaction fixture', 'body' => 'Real opinion', 'is_approved' => true, 'interaction_conversation_id' => 999])->assertRedirect(route('reviews.mine'));
        $review = Review::where('author_id', $this->buyer->id)->firstOrFail();
        $this->assertFalse($review->is_approved);
        $this->assertNull($review->reviewed_at);
        $this->assertSame($thread->id, (int) $review->interaction_conversation_id);
        $this->assertTrue($review->hasInteractionEvidence());
        $this->assertSame(0, Review::summaryForSeller($this->seller->id)['total']);
        $this->get(route('sellers.show', $this->seller))->assertDontSee('Verified interaction fixture');
        $this->get(route('reviews.mine'))->assertOk()->assertSee('Verified interaction fixture')->assertHeader('Cache-Control', 'no-store, private');
        $review->approve();
        $this->assertSame(1, Review::summaryForSeller($this->seller->id)['total']);
        $this->get(route('sellers.show', $this->seller))->assertSee('Verified interaction fixture')->assertSee('data-review-interaction', false)->assertDontSee('PRIVATE CONVERSATION TEXT')->assertDontSee('interaction_conversation_id');
        $review->reject();
        $this->assertNotNull($review->fresh()->reviewed_at);
        $this->assertSame(0, Review::summaryForSeller($this->seller->id)['total']);
    }

    public function test_one_way_future_third_party_and_deleted_messages_do_not_create_evidence(): void
    {
        $thread = $this->conversation(false);
        $this->assertNull(Conversation::reviewEvidenceFor($this->buyer->id, $this->seller->id, null));
        ConversationMessage::unguarded(fn () => ConversationMessage::create(['conversation_id' => $thread->id, 'sender_id' => $this->account()->id, 'body' => 'Third party', 'created_at' => now()->subHour()]));
        $reply = ConversationMessage::unguarded(fn () => ConversationMessage::create(['conversation_id' => $thread->id, 'sender_id' => $this->seller->id, 'body' => 'Future', 'created_at' => now()->addDay()]));
        $this->assertNull(Conversation::reviewEvidenceFor($this->buyer->id, $this->seller->id, null));
        $reply->forceFill(['created_at' => now()->subHour()])->save();
        $reply->delete();
        $this->assertFalse($this->submit()->hasInteractionEvidence());
    }

    public function test_duplicate_general_and_listing_reviews_are_rejected_while_pending(): void
    {
        $this->submit();
        $this->actingAs($this->buyer)->post(route('reviews.store', $this->seller), ['rating' => 1])->assertSessionHasErrors('rating');
        $this->submit($this->listing->id);
        $this->post(route('reviews.store', $this->seller), ['rating' => 2, 'listing_id' => $this->listing->id])->assertSessionHasErrors('rating');
        $this->assertSame(2, Review::where('author_id', $this->buyer->id)->count());
    }

    public function test_wrong_seller_listing_and_self_or_inactive_authors_cannot_submit(): void
    {
        $other = $this->account();
        $this->actingAs($this->buyer)->post(route('reviews.store', $other), ['rating' => 5, 'listing_id' => $this->listing->id])->assertSessionHasErrors('listing_id');
        $this->post(route('reviews.store', $this->buyer), ['rating' => 5])->assertSessionHas('error');
        $this->buyer->forceFill(['status' => 'suspended'])->save();
        $this->post(route('reviews.store', $this->seller), ['rating' => 5])->assertForbidden();
        $this->assertSame(0, Review::where('author_id', $this->buyer->id)->count());
    }

    public function test_my_reviews_and_delete_are_owner_scoped_and_do_not_disclose_other_bodies(): void
    {
        $review = $this->submit();
        $this->actingAs($this->seller)->get(route('reviews.mine'))->assertOk()->assertDontSee('Review body');
        $this->delete(route('reviews.destroy', $review))->assertForbidden();
        $this->actingAs($this->buyer)->delete(route('reviews.destroy', $review))->assertRedirect();
        $this->assertSoftDeleted('reviews', ['id' => $review->id]);
    }

    public function test_evidence_is_snapshot_not_retroactive_and_identity_edits_clear_it(): void
    {
        $legacy = $this->submit();
        $this->conversation();
        $this->assertFalse($legacy->fresh()->hasInteractionEvidence());
        $review = $this->submit($this->listing->id);
        $this->assertTrue($review->hasInteractionEvidence());
        $review->forceFill(['seller_id' => $this->account()->id])->save();
        $this->assertFalse($review->fresh()->hasInteractionEvidence());
    }

    public function test_general_evidence_does_not_transfer_to_a_different_listing(): void
    {
        $this->conversation();
        $other = $this->listing->replicate(['slug']);
        $other->slug = (string) Str::uuid();
        $other->save();
        $this->assertFalse($this->submit($other->id)->hasInteractionEvidence());
        $this->assertTrue($this->submit()->hasInteractionEvidence());
    }
}
