<?php

declare(strict_types=1);

namespace Tests\Feature;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Category\Models\Category;
use Modules\Conversation\App\Models\Conversation;
use Modules\Conversation\App\Models\ConversationBlock;
use Modules\Conversation\App\Models\ConversationVisit;
use Modules\Listing\Models\Listing;
use Modules\Notification\Models\UserNotification;
use Modules\User\App\Models\User;
use Tests\TestCase;

final class ConversationVisitTest extends TestCase
{
    private User $buyer;

    private User $seller;

    private Listing $listing;

    private Conversation $thread;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(now()->startOfMinute());
        $this->buyer = $this->account();
        $this->seller = $this->account();
        $this->listing = $this->listingFor($this->seller);
        $this->thread = Conversation::openForListingBuyer($this->listing, $this->buyer->id);
    }

    protected function tearDown(): void
    {
        $this->travelBack();
        parent::tearDown();
    }

    private function account(): User
    {
        return User::create(['name' => 'Visit fixture', 'email' => Str::uuid().'@example.test', 'password' => 'visit-fixture-password', 'status' => 'active']);
    }

    private function listingFor(User $seller): Listing
    {
        return Listing::unguarded(fn () => Listing::create(['title' => 'Visit listing fixture', 'description' => 'Fixture', 'slug' => (string) Str::uuid(), 'user_id' => $seller->id, 'category_id' => Category::firstOrFail()->id, 'price' => 100, 'currency' => 'IRR', 'status' => 'active']));
    }

    private function propose(?Conversation $thread = null, ?int $actor = null, ?CarbonImmutable $time = null): ConversationVisit
    {
        return ConversationVisit::proposeFor($thread ?? $this->thread, $actor ?? $this->buyer->id, $time ?? CarbonImmutable::now()->addDay(), 'PRIVATE MEETING POINT', 'PRIVATE VISIT NOTE');
    }

    private function payload(): array
    {
        return ['visit_time' => CarbonImmutable::now('Asia/Tehran')->addDay()->format('Y-m-d\TH:i'), 'meeting_place' => 'PRIVATE MEETING POINT', 'visit_note' => 'PRIVATE VISIT NOTE', 'visit_confirmed' => '1'];
    }

    public function test_proposal_uses_tehran_time_encrypts_details_and_notifies_only_peer(): void
    {
        $data = $this->payload();
        $this->actingAs($this->buyer)->post(route('conversations.visits.store', $this->thread), $data + ['proposed_by' => $this->seller->id, 'status' => 'accepted'])->assertRedirect(route('conversations.visits.index', $this->thread));
        $visit = ConversationVisit::where('conversation_id', $this->thread->id)->sole();
        $this->assertSame('pending', $visit->status);
        $this->assertSame($this->buyer->id, (int) $visit->proposed_by);
        $expected = CarbonImmutable::createFromFormat('Y-m-d\TH:i', $data['visit_time'], 'Asia/Tehran');
        $this->assertTrue($expected->startOfMinute()->equalTo($visit->starts_at));
        $this->assertSame(30.0, $visit->starts_at->diffInMinutes($visit->ends_at));
        $this->assertSame('PRIVATE MEETING POINT', $visit->meeting_place);
        $this->assertStringNotContainsString('PRIVATE MEETING POINT', DB::table('conversation_visits')->where('id', $visit->id)->value('meeting_place'));
        $this->assertStringNotContainsString('PRIVATE VISIT NOTE', DB::table('conversation_visits')->where('id', $visit->id)->value('note'));
        $notification = UserNotification::where('user_id', $this->seller->id)->sole();
        $this->assertSame(route('conversations.visits.index', $this->thread), $notification->action_url);
        $this->assertStringNotContainsString('PRIVATE', $notification->body);
        $this->assertSame(0, $this->thread->messages()->count());
        $this->assertNull(Conversation::sellerResponseSummary($this->seller->id));
    }

    public function test_other_participant_accepts_once_and_either_can_cancel_without_erasing_acceptance(): void
    {
        $visit = $this->propose();
        $url = route('conversations.visits.decide', [$this->thread, $visit]);
        $this->actingAs($this->buyer)->postJson($url, ['decision' => 'accepted', 'visit_confirmed' => '1'])->assertForbidden();
        $this->actingAs($this->seller)->postJson($url, ['decision' => 'accepted'])->assertUnprocessable()->assertJsonValidationErrors('visit_confirmed');
        $this->post($url, ['decision' => 'accepted', 'visit_confirmed' => '1'])->assertRedirect();
        $acceptedAt = $visit->fresh()->accepted_at;
        $this->assertNotNull($acceptedAt);
        $this->postJson($url, ['decision' => 'accepted', 'visit_confirmed' => '1'])->assertConflict();
        $this->assertSame(1, UserNotification::where('user_id', $this->buyer->id)->count());
        $this->actingAs($this->buyer)->post($url, ['decision' => 'cancelled', 'visit_confirmed' => '1'])->assertRedirect();
        $this->assertSame('cancelled', $visit->fresh()->status);
        $this->assertTrue($acceptedAt->equalTo($visit->fresh()->accepted_at));
        $this->postJson($url, ['decision' => 'cancelled', 'visit_confirmed' => '1'])->assertConflict();
        $this->assertSame(2, UserNotification::where('user_id', $this->seller->id)->count());
        $this->assertSame('active', $this->listing->fresh()->statusValue());
    }

    public function test_seller_can_propose_and_buyer_declines_then_a_new_time_preserves_history(): void
    {
        $visit = $this->propose(actor: $this->seller->id);
        $visit->decideFor($this->thread, $this->buyer->id, 'declined');
        $this->assertSame('declined', $visit->fresh()->status);
        $next = $this->propose();
        $this->assertSame('pending', $next->status);
        $this->assertSame(2, ConversationVisit::where('conversation_id', $this->thread->id)->count());
    }

    public function test_duplicate_upcoming_proposals_are_rejected_without_extra_notification(): void
    {
        $visit = $this->propose();
        $this->actingAs($this->seller)->postJson(route('conversations.visits.store', $this->thread), $this->payload())->assertUnprocessable()->assertJsonValidationErrors('visit_time');
        $this->assertSame(1, ConversationVisit::where('conversation_id', $this->thread->id)->count());
        $this->assertSame(0, UserNotification::where('user_id', $this->buyer->id)->count());
        $visit->decideFor($this->thread, $this->seller->id, 'accepted');
        $this->postJson(route('conversations.visits.store', $this->thread), $this->payload())->assertUnprocessable();
    }

    public function test_conflicting_accepted_visit_is_rechecked_at_acceptance_and_adjacent_times_are_allowed(): void
    {
        $otherBuyer = $this->account();
        $thread = Conversation::openForListingBuyer($this->listing, $otherBuyer->id);
        $first = $this->propose();
        $second = $this->propose($thread, $otherBuyer->id);
        $first->decideFor($this->thread, $this->seller->id, 'accepted');
        $this->actingAs($this->seller)->postJson(route('conversations.visits.decide', [$thread, $second]), ['decision' => 'accepted', 'visit_confirmed' => '1'])->assertUnprocessable()->assertJsonValidationErrors('visit_time');
        $this->assertSame('pending', $second->fresh()->status);
        $second->decideFor($thread, $otherBuyer->id, 'cancelled');
        $adjacent = $this->propose($thread, $otherBuyer->id, $first->ends_at);
        $adjacent->decideFor($thread, $this->seller->id, 'accepted');
        $this->assertSame('accepted', $adjacent->fresh()->status);
    }

    public function test_overlap_checks_buyer_when_sellers_differ_and_does_not_expose_other_meeting_details(): void
    {
        $first = $this->propose();
        $first->decideFor($this->thread, $this->seller->id, 'accepted');
        $otherSeller = $this->account();
        $listing = $this->listingFor($otherSeller);
        $thread = Conversation::openForListingBuyer($listing, $this->buyer->id);
        $this->actingAs($otherSeller)->postJson(route('conversations.visits.store', $thread), $this->payload())->assertUnprocessable()->assertJsonValidationErrors('visit_time')->assertDontSee('PRIVATE MEETING POINT');
    }

    public function test_outsiders_guests_and_other_threads_cannot_read_or_decide(): void
    {
        $visit = $this->propose();
        $index = route('conversations.visits.index', $this->thread);
        $this->get($index)->assertRedirect(route('login'));
        $outsider = $this->account();
        $this->actingAs($outsider)->get($index)->assertForbidden()->assertDontSee('PRIVATE MEETING POINT');
        $this->postJson(route('conversations.visits.store', $this->thread), $this->payload())->assertForbidden();
        $this->postJson(route('conversations.visits.decide', [$this->thread, $visit]), ['decision' => 'cancelled', 'visit_confirmed' => '1'])->assertForbidden();
        $thread = Conversation::openForListingBuyer($this->listing, $outsider->id);
        $this->actingAs($this->seller)->postJson(route('conversations.visits.decide', [$thread, $visit]), ['decision' => 'accepted', 'visit_confirmed' => '1'])->assertNotFound();
        $this->assertSame('pending', $visit->fresh()->status);
    }

    public function test_block_prevents_propose_accept_and_decline_but_allows_silent_cancellation(): void
    {
        $visit = $this->propose();
        ConversationBlock::setFor($this->seller, $this->buyer->id, true);
        $this->actingAs($this->seller)->postJson(route('conversations.visits.store', $this->thread), $this->payload())->assertUnprocessable();
        foreach (['accepted', 'declined'] as $decision) {
            $this->postJson(route('conversations.visits.decide', [$this->thread, $visit]), ['decision' => $decision, 'visit_confirmed' => '1'])->assertUnprocessable();
        }
        $this->post(route('conversations.visits.decide', [$this->thread, $visit]), ['decision' => 'cancelled', 'visit_confirmed' => '1'])->assertRedirect();
        $this->assertSame('cancelled', $visit->fresh()->status);
        $this->assertSame(0, UserNotification::where('user_id', $this->buyer->id)->count());
        $this->assertSame(1, UserNotification::where('user_id', $this->seller->id)->count());
    }

    public function test_inactive_actor_cannot_change_visits_but_active_peer_can_cancel_silently(): void
    {
        $visit = $this->propose();
        $this->seller->forceFill(['status' => 'suspended'])->save();
        $url = route('conversations.visits.decide', [$this->thread, $visit]);
        $this->actingAs($this->seller)->postJson($url, ['decision' => 'cancelled', 'visit_confirmed' => '1'])->assertForbidden();
        $this->actingAs($this->buyer)->postJson($url, ['decision' => 'accepted', 'visit_confirmed' => '1'])->assertForbidden();
        $this->post($url, ['decision' => 'cancelled', 'visit_confirmed' => '1'])->assertRedirect();
        $this->assertSame('cancelled', $visit->fresh()->status);
        $this->assertSame(1, UserNotification::where('user_id', $this->seller->id)->count());
    }

    public function test_invalid_time_details_and_missing_confirmation_never_create_a_visit(): void
    {
        $this->actingAs($this->buyer);
        $url = route('conversations.visits.store', $this->thread);
        foreach (['not-a-time', CarbonImmutable::now('Asia/Tehran')->subDay()->format('Y-m-d\TH:i'), CarbonImmutable::now('Asia/Tehran')->addMinutes(14)->format('Y-m-d\TH:i'), CarbonImmutable::now('Asia/Tehran')->addDays(91)->format('Y-m-d\TH:i')] as $time) {
            $this->postJson($url, array_replace($this->payload(), ['visit_time' => $time]))->assertUnprocessable()->assertJsonValidationErrors('visit_time');
        }
        $this->postJson($url, array_replace($this->payload(), ['meeting_place' => '   ']))->assertUnprocessable();
        $this->postJson($url, array_replace($this->payload(), ['visit_note' => str_repeat('x', 501)]))->assertUnprocessable()->assertJsonValidationErrors('visit_note');
        $this->postJson($url, array_replace($this->payload(), ['visit_confirmed' => '0']))->assertUnprocessable()->assertJsonValidationErrors('visit_confirmed');
        $this->assertSame(0, ConversationVisit::where('conversation_id', $this->thread->id)->count());
    }

    public function test_unavailable_changed_owner_and_deleted_listings_prevent_creation_and_acceptance(): void
    {
        $visit = $this->propose();
        $this->actingAs($this->seller);
        foreach (['paused', 'sold', 'pending', 'expired', 'scheduled'] as $status) {
            $this->listing->forceFill(['status' => $status, 'publish_at' => $status === 'scheduled' ? now()->addDay() : null])->save();
            $this->postJson(route('conversations.visits.decide', [$this->thread, $visit]), ['decision' => 'accepted', 'visit_confirmed' => '1'])->assertConflict();
        }
        $this->listing->forceFill(['status' => 'active', 'publish_at' => null, 'user_id' => $this->account()->id])->save();
        $this->postJson(route('conversations.visits.decide', [$this->thread, $visit]), ['decision' => 'accepted', 'visit_confirmed' => '1'])->assertConflict();
        $this->listing->delete();
        $this->postJson(route('conversations.visits.store', $this->thread), $this->payload())->assertConflict();
        $this->assertSame('pending', $visit->fresh()->status);
    }

    public function test_past_proposals_cannot_be_accepted_and_new_proposals_need_no_cron(): void
    {
        $visit = $this->propose();
        $this->travelTo($visit->starts_at->addMinute());
        $this->actingAs($this->seller)->postJson(route('conversations.visits.decide', [$this->thread, $visit]), ['decision' => 'accepted', 'visit_confirmed' => '1'])->assertConflict();
        $this->assertSame('past', $visit->fresh()->displayStatus());
        $this->assertFalse(ConversationVisit::hasUpcomingFor($this->thread));
        $next = $this->propose();
        $this->assertSame('pending', $next->status);
    }

    public function test_completed_time_is_not_a_verified_visit_and_history_is_paginated_without_deleted_records(): void
    {
        $visit = $this->propose();
        $visit->decideFor($this->thread, $this->seller->id, 'accepted');
        $this->travelTo($visit->ends_at->addMinute());
        $this->assertSame('past', $visit->fresh()->displayStatus());
        $this->assertFalse(ConversationVisit::hasUpcomingFor($this->thread));
        for ($i = 0; $i < 11; $i++) {
            $next = $this->propose();
            $next->decideFor($this->thread, $this->buyer->id, 'cancelled');
        }
        $page = ConversationVisit::forConversation($this->thread, $this->seller->id);
        $this->assertSame(12, $page->total());
        $this->assertCount(10, $page->items());
        $this->assertSame(2, $page->lastPage());
        $next->delete();
        $this->assertSame(11, ConversationVisit::forConversation($this->thread, $this->seller->id)->total());
        $this->assertSame(0, $this->thread->messages()->count());
    }

    public function test_private_page_escapes_details_keeps_old_input_and_links_from_inbox(): void
    {
        $url = route('conversations.visits.index', $this->thread);
        $this->actingAs($this->buyer)->get(route('panel.inbox.index', ['conversation' => $this->thread->id]))->assertOk()->assertSee($url, false);
        $this->from($url)->post(route('conversations.visits.store', $this->thread), array_replace($this->payload(), ['visit_time' => 'invalid']))->assertRedirect($url)->assertSessionHasErrors('visit_time');
        $this->get($url)->assertOk()->assertSee('PRIVATE MEETING POINT');
        $visit = ConversationVisit::proposeFor($this->thread, $this->buyer->id, CarbonImmutable::now()->addDay(), '<script>bad()</script>', null);
        $response = $this->get($url)->assertOk()->assertSee('&lt;script&gt;bad()&lt;/script&gt;', false)->assertDontSee('<script>bad()</script>', false);
        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
        $this->assertSame('noindex, nofollow', $response->headers->get('X-Robots-Tag'));
        $this->assertSame(0, $this->thread->messages()->count());
        $this->assertSame($visit->id, ConversationVisit::forConversation($this->thread, $this->seller->id)->first()->id);
    }
}
