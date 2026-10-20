<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Support\Str;
use Modules\Category\Models\Category;
use Modules\Conversation\App\Models\Conversation;
use Modules\Conversation\App\Models\ConversationBlock;
use Modules\Listing\Models\Listing;
use Modules\Notification\Models\UserNotification;
use Modules\Offer\Models\Offer;
use Modules\User\App\Models\User;
use Tests\TestCase;

final class ConversationOfferTest extends TestCase
{
    private User $buyer;

    private User $seller;

    private Listing $listing;

    private Conversation $thread;

    protected function setUp(): void
    {
        parent::setUp();
        $this->buyer = $this->account();
        $this->seller = $this->account();
        $this->listing = Listing::unguarded(fn () => Listing::create(['title' => 'Chat price fixture', 'description' => 'Fixture', 'slug' => (string) Str::uuid(), 'user_id' => $this->seller->id, 'category_id' => Category::firstOrFail()->id, 'price' => 100000, 'currency' => 'IRR', 'status' => 'active']));
        $this->thread = Conversation::openForListingBuyer($this->listing, $this->buyer->id);
    }

    private function account(): User
    {
        return User::create(['name' => 'Chat offer fixture', 'email' => Str::uuid().'@example.test', 'password' => 'fixture-password', 'status' => 'active']);
    }

    private function place(string $amount = '90000', ?string $message = null): Offer
    {
        return Offer::placeInConversation($this->thread, $this->buyer->id, $amount, $message);
    }

    public function test_buyer_creates_precise_rial_offer_and_only_seller_is_notified_in_chat(): void
    {
        $this->actingAs($this->buyer)->post(route('conversation-offers.store', $this->thread), ['amount' => '۱۲۳٬۴۵۶٬۷۸۹٬۱۲۳٬۴۵۶٬۷۸۹', 'currency' => 'USD', 'seller_id' => $this->buyer->id, 'message' => '<script>alert(1)</script>'])->assertRedirect(route('panel.inbox.index', ['conversation' => $this->thread->id]));
        $offer = Offer::where('listing_id', $this->listing->id)->firstOrFail();
        $this->assertSame('123456789123456789.00', $offer->amount);
        $this->assertSame('IRR', $offer->currency);
        $this->assertSame($this->seller->id, (int) $offer->seller_id);
        $this->assertSame(0, $this->thread->messages()->count());
        $notification = UserNotification::where('user_id', $this->seller->id)->sole();
        $this->assertSame(route('panel.inbox.index', ['conversation' => $this->thread->id]), $notification->action_url);
        $this->get(route('panel.inbox.index', ['conversation' => $this->thread->id]))->assertOk()->assertSee('chat-offer-amount', false)->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false)->assertDontSee('<script>alert(1)</script>', false);
    }

    public function test_new_offer_replaces_only_same_pair_and_listing_pending_offer_and_preserves_history(): void
    {
        $first = $this->place();
        $otherBuyer = $this->account();
        $other = Offer::place($this->listing->id, $otherBuyer->id, $this->seller->id, '88000', 'IRR', null);
        $second = $this->place('95000');
        $this->assertSame(Offer::STATUS_WITHDRAWN, $first->fresh()->status);
        $this->assertNotNull($first->fresh()->responded_at);
        $this->assertTrue($second->isPending());
        $this->assertTrue($other->fresh()->isPending());
        $this->assertSame([$second->id, $first->id], Offer::inConversation($this->thread, $this->buyer->id)->modelKeys());
        $this->assertSame(0, $this->thread->messages()->count());
    }

    public function test_seller_accepts_once_and_stale_decline_or_withdraw_does_not_mutate_or_notify(): void
    {
        $offer = $this->place();
        $url = route('conversation-offers.respond', [$this->thread, $offer]);
        $this->actingAs($this->seller)->post($url, ['decision' => 'accepted', 'confirmed' => '1'])->assertRedirect();
        $this->assertSame(Offer::STATUS_ACCEPTED, $offer->fresh()->status);
        $this->assertSame(1, UserNotification::where('user_id', $this->buyer->id)->count());
        $this->post($url, ['decision' => 'declined', 'confirmed' => '1'])->assertConflict();
        $this->actingAs($this->buyer)->post($url, ['decision' => 'withdrawn', 'confirmed' => '1'])->assertConflict();
        $this->assertSame(Offer::STATUS_ACCEPTED, $offer->fresh()->status);
        $this->assertSame(1, UserNotification::where('user_id', $this->buyer->id)->count());
        $this->assertSame('active', $this->listing->fresh()->statusValue());
    }

    public function test_decline_and_withdraw_work_without_javascript_and_require_explicit_confirmation(): void
    {
        $offer = $this->place();
        $url = route('conversation-offers.respond', [$this->thread, $offer]);
        $this->actingAs($this->seller)->postJson($url, ['decision' => 'declined'])->assertUnprocessable()->assertJsonValidationErrors('confirmed');
        $this->post($url, ['decision' => 'declined', 'confirmed' => '1'])->assertRedirect();
        $this->assertSame(Offer::STATUS_DECLINED, $offer->fresh()->status);
        $next = $this->place();
        $this->actingAs($this->buyer)->post(route('conversation-offers.respond', [$this->thread, $next]), ['decision' => 'withdrawn', 'confirmed' => '1'])->assertRedirect();
        $this->assertSame(Offer::STATUS_WITHDRAWN, $next->fresh()->status);
    }

    public function test_outsider_other_thread_and_wrong_roles_are_denied(): void
    {
        $offer = $this->place();
        $other = $this->account();
        $snapshot = route('conversation-offers.snapshot', $this->thread);
        $store = route('conversation-offers.store', $this->thread);
        $respond = route('conversation-offers.respond', [$this->thread, $offer]);
        $this->actingAs($other)->getJson($snapshot)->assertForbidden();
        $this->postJson($store, ['amount' => '1'])->assertForbidden();
        $this->postJson($respond, ['decision' => 'accepted', 'confirmed' => '1'])->assertForbidden();
        $otherThread = Conversation::openForListingBuyer($this->listing, $other->id);
        $this->actingAs($this->seller)->postJson(route('conversation-offers.respond', [$otherThread, $offer]), ['decision' => 'accepted', 'confirmed' => '1'])->assertNotFound();
        $this->postJson($store, ['amount' => '1'])->assertForbidden();
        $this->postJson($respond, ['decision' => 'withdrawn', 'confirmed' => '1'])->assertForbidden();
        $this->actingAs($this->buyer)->postJson($respond, ['decision' => 'accepted', 'confirmed' => '1'])->assertForbidden();
        $this->assertTrue($offer->fresh()->isPending());
    }

    public function test_block_protects_chat_and_legacy_offer_endpoints_but_owner_can_withdraw(): void
    {
        $offer = $this->place();
        ConversationBlock::setFor($this->buyer, $this->seller->id, true);
        $this->actingAs($this->buyer)->postJson(route('conversation-offers.store', $this->thread), ['amount' => '1'])->assertUnprocessable();
        $this->postJson(route('offers.store', $this->listing), ['amount' => '1'])->assertUnprocessable();
        $this->actingAs($this->seller)->postJson(route('offers.accept', $offer))->assertUnprocessable();
        $this->postJson(route('offers.decline', $offer))->assertUnprocessable();
        $this->postJson(route('conversation-offers.respond', [$this->thread, $offer]), ['decision' => 'accepted', 'confirmed' => '1'])->assertUnprocessable();
        $this->assertSame(0, UserNotification::where('user_id', $this->buyer->id)->count());
        $this->assertSame(1, Offer::where('listing_id', $this->listing->id)->count());
        $this->actingAs($this->buyer)->post(route('offers.withdraw', $offer))->assertRedirect();
        $this->assertSame(Offer::STATUS_WITHDRAWN, $offer->fresh()->status);
    }

    public function test_invalid_amounts_never_replace_pending_offer(): void
    {
        $offer = $this->place();
        $this->actingAs($this->buyer);
        foreach (['0', '-1', '1.5', '1e5', '1000000000000000000', ['1']] as $amount) {
            $this->postJson(route('conversation-offers.store', $this->thread), ['amount' => $amount])->assertUnprocessable()->assertJsonValidationErrors('amount');
            $this->assertTrue($offer->fresh()->isPending());
        }
        $this->postJson(route('conversation-offers.store', $this->thread), ['amount' => '1', 'message' => str_repeat('x', 501)])->assertUnprocessable()->assertJsonValidationErrors('message');
        $this->assertSame(1, Offer::where('listing_id', $this->listing->id)->count());
    }

    public function test_changed_listing_currency_or_owner_or_unavailable_status_prevents_acceptance(): void
    {
        $offer = $this->place();
        $this->actingAs($this->seller);
        foreach (['paused', 'sold', 'scheduled', 'expired', 'pending'] as $status) {
            $this->listing->forceFill(['status' => $status, 'publish_at' => $status === 'scheduled' ? now()->addDay() : null])->save();
            $this->postJson(route('offers.accept', $offer))->assertConflict();
            $this->assertTrue($offer->fresh()->isPending());
        }
        $this->listing->forceFill(['status' => 'active', 'currency' => 'USD'])->save();
        $this->postJson(route('offers.accept', $offer))->assertConflict();
        $this->listing->forceFill(['currency' => 'IRR', 'user_id' => $this->account()->id])->save();
        $this->postJson(route('offers.accept', $offer))->assertConflict();
        $this->assertTrue($offer->fresh()->isPending());
        $this->assertSame(0, UserNotification::where('user_id', $this->buyer->id)->count());
    }

    public function test_snapshot_is_private_bounded_and_includes_legacy_offers_without_synthetic_messages(): void
    {
        for ($i = 0; $i < 5; $i++) {
            Offer::place($this->listing->id, $this->buyer->id, $this->seller->id, (string) (100 + $i), 'USD', 'Legacy-'.$i);
        }
        $response = $this->actingAs($this->seller)->getJson(route('conversation-offers.snapshot', $this->thread))->assertOk();
        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
        $html = $response->json('html');
        $this->assertSame(3, substr_count($html, 'data-chat-offer='));
        $this->assertStringContainsString('Legacy-4', $html);
        $this->assertStringNotContainsString('Legacy-0', $html);
        $this->assertSame(0, $this->thread->messages()->count());
        $this->assertNull(Conversation::sellerResponseSummary($this->seller->id));
    }

    public function test_inactive_account_deleted_listing_and_expired_listing_are_rejected(): void
    {
        $offer = $this->place();
        $this->buyer->forceFill(['status' => 'suspended'])->save();
        $this->actingAs($this->seller)->postJson(route('offers.accept', $offer))->assertForbidden();
        $this->buyer->forceFill(['status' => 'active'])->save();
        $this->listing->forceFill(['expires_at' => now()->subMinute()])->save();
        $this->postJson(route('offers.accept', $offer))->assertConflict();
        $this->actingAs($this->buyer)->postJson(route('conversation-offers.store', $this->thread), ['amount' => '1'])->assertUnprocessable();
        $this->listing->delete();
        $this->actingAs($this->seller)->postJson(route('offers.accept', $offer))->assertConflict();
        $this->actingAs($this->buyer)->postJson(route('conversation-offers.store', $this->thread), ['amount' => '1'])->assertNotFound();
        $this->assertTrue($offer->fresh()->isPending());
    }
}
