<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Category\Models\Category;
use Modules\Conversation\App\Models\Conversation;
use Modules\Conversation\App\Models\ConversationBlock;
use Modules\Listing\Models\Listing;
use Modules\Report\Models\Report;
use Modules\User\App\Models\User;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

final class ConversationSafetyTest extends TestCase
{
    private User $buyer;

    private User $seller;

    private Conversation $thread;

    private Listing $listing;

    protected function setUp(): void
    {
        parent::setUp();
        $this->buyer = $this->account();
        $this->seller = $this->account();
        $this->listing = $this->listing();
        $this->thread = Conversation::openForListingBuyer($this->listing, $this->buyer->id);
    }

    private function account(): User
    {
        return User::create(['name' => 'Safety fixture', 'email' => Str::uuid().'@example.test', 'password' => 'fixture-password', 'status' => 'active']);
    }

    private function listing(): Listing
    {
        return Listing::unguarded(fn () => Listing::create(['title' => 'Safety listing', 'description' => 'Fixture', 'slug' => (string) Str::uuid(), 'user_id' => $this->seller->id, 'category_id' => Category::firstOrFail()->id, 'price' => 100, 'currency' => 'IRR', 'status' => 'active']));
    }

    public function test_block_stops_both_directions_and_new_listing_conversations_but_keeps_history(): void
    {
        $this->thread->createMessageFor($this->seller->id, 'Existing message');
        $this->actingAs($this->buyer)->post(route('conversations.block', $this->thread), ['confirmed' => '1', 'blocked' => '1'])->assertRedirect();
        $this->assertTrue(ConversationBlock::between($this->buyer->id, $this->seller->id));
        foreach ([$this->buyer, $this->seller] as $sender) {
            $this->actingAs($sender)->postJson(route('conversations.messages.send', $this->thread), ['message' => 'Blocked message'])->assertUnprocessable()->assertJsonValidationErrors('message');
        }
        $another = $this->listing();
        $this->actingAs($this->buyer)->postJson(route('conversations.start', $another), ['message' => 'Bypass attempt'])->assertUnprocessable();
        $this->assertFalse(Conversation::where('listing_id', $another->id)->exists());
        $this->assertSame(1, $this->thread->messages()->count());
        $this->get(route('panel.inbox.index', ['conversation' => $this->thread->id]))->assertOk()->assertSee('Existing message')->assertDontSee('data-thread-form', false);
    }

    public function test_unblock_is_directional_idempotent_and_reverse_block_is_preserved(): void
    {
        ConversationBlock::setFor($this->buyer, $this->seller->id, true);
        ConversationBlock::setFor($this->buyer, $this->seller->id, true);
        $this->assertSame(1, ConversationBlock::count());
        ConversationBlock::setFor($this->seller, $this->buyer->id, true);
        ConversationBlock::setFor($this->buyer, $this->seller->id, false);
        $this->assertTrue(ConversationBlock::between($this->buyer->id, $this->seller->id));
        $this->assertFalse(ConversationBlock::owned($this->buyer->id, $this->seller->id));
        ConversationBlock::setFor($this->seller, $this->buyer->id, false);
        $this->assertFalse(ConversationBlock::between($this->buyer->id, $this->seller->id));
        $message = $this->thread->createMessageFor($this->buyer->id, 'Allowed again');
        $this->assertSame('Allowed again', $message->body);
        $this->assertSame(2, ConversationBlock::onlyTrashed()->count());
    }

    public function test_outsiders_cannot_read_control_or_report_a_conversation_and_self_block_is_denied(): void
    {
        $other = $this->account();
        $this->actingAs($other)->get(route('conversations.safety', $this->thread))->assertForbidden();
        $this->post(route('conversations.block', $this->thread), ['blocked' => '1', 'confirmed' => '1'])->assertForbidden();
        $this->post(route('conversations.report', $this->thread), ['reason' => 'spam', 'confirmed' => '1'])->assertForbidden();
        $this->assertSame(0, Report::where('reporter_id', $other->id)->count());
        $this->expectException(HttpException::class);
        ConversationBlock::setFor($other, $other->id, true);
    }

    public function test_report_snapshots_only_selected_peer_message_encrypted_without_blocking(): void
    {
        $selected = $this->thread->createMessageFor($this->seller->id, 'REPORTED PRIVATE BODY');
        $this->thread->createMessageFor($this->seller->id, 'UNSELECTED PRIVATE BODY');
        $this->actingAs($this->buyer)->post(route('conversations.report', $this->thread), ['reason' => 'offensive', 'message_id' => $selected->id, 'details' => 'Please review this', 'confirmed' => '1'])->assertRedirect();
        $report = Report::where('conversation_id', $this->thread->id)->firstOrFail();
        $this->assertSame('REPORTED PRIVATE BODY', $report->conversation_evidence);
        $this->assertSame($this->seller->id, (int) $report->subject_id);
        $this->assertSame('user', $report->subject_type);
        $this->assertSame('open', $report->status);
        $raw = DB::table('reports')->where('id', $report->id)->value('conversation_evidence');
        $this->assertStringNotContainsString('REPORTED PRIVATE BODY', $raw);
        $this->assertFalse(ConversationBlock::between($this->buyer->id, $this->seller->id));
        $selected->forceFill(['body' => 'Edited later'])->save();
        $this->assertSame('REPORTED PRIVATE BODY', $report->fresh()->conversation_evidence);
        $again = Report::fileConversationFor($this->buyer, $this->thread, $selected->id, 'spam', null);
        $this->assertSame($report->id, $again->id);
        $this->get('/admin/reports/'.$report->id.'/edit')->assertForbidden();
    }

    public function test_foreign_own_and_deleted_messages_cannot_be_reported_as_peer_evidence(): void
    {
        $own = $this->thread->createMessageFor($this->buyer->id, 'Own message');
        $otherThread = Conversation::openForListingBuyer($this->listing(), $this->buyer->id);
        $foreign = $otherThread->createMessageFor($this->seller->id, 'Other conversation');
        $deleted = $this->thread->createMessageFor($this->seller->id, 'Deleted message');
        $deleted->delete();
        $this->actingAs($this->buyer);
        foreach ([$own->id, $foreign->id, $deleted->id, 99999999] as $id) {
            $this->post(route('conversations.report', $this->thread), ['reason' => 'spam', 'message_id' => $id, 'confirmed' => '1'])->assertSessionHasErrors('message_id');
        }
        $this->assertSame(0, Report::where('conversation_id', $this->thread->id)->count());
    }

    public function test_general_reports_and_block_confirmation_private_list_and_unblock_ownership(): void
    {
        $this->actingAs($this->buyer)->post(route('conversations.block', $this->thread), ['blocked' => '1'])->assertSessionHasErrors('confirmed');
        $this->get(route('conversations.safety', $this->thread))->assertOk()->assertHeader('Cache-Control', 'no-store, private');
        $this->post(route('conversations.report', $this->thread), ['reason' => 'spam', 'confirmed' => '1'])->assertRedirect();
        $report = Report::where('conversation_id', $this->thread->id)->firstOrFail();
        $this->assertNull($report->conversation_evidence);
        ConversationBlock::setFor($this->buyer, $this->seller->id, true);
        $this->get(route('panel.blocked-users'))->assertOk()->assertViewHas('blocks', fn ($blocks) => $blocks->total() === 1);
        $other = $this->account();
        $this->actingAs($other)->post(route('panel.blocked-users.unblock', $this->seller), ['confirmed' => '1'])->assertRedirect();
        $this->assertTrue(ConversationBlock::owned($this->buyer->id, $this->seller->id));
        $this->actingAs($this->buyer)->post(route('panel.blocked-users.unblock', $this->seller), ['confirmed' => '1'])->assertRedirect();
        $this->assertFalse(ConversationBlock::between($this->buyer->id, $this->seller->id));
    }

    public function test_changed_listing_owner_cannot_inherit_an_existing_private_conversation(): void
    {
        $this->thread->createMessageFor($this->seller->id, 'Original participant history');
        $replacement = $this->account();
        $this->listing->forceFill(['user_id' => $replacement->id])->save();
        $this->actingAs($this->buyer)->postJson(route('conversations.start', $this->listing), ['message' => 'New contact'])->assertConflict();
        $this->assertSame($this->seller->id, (int) $this->thread->fresh()->seller_id);
        $this->actingAs($replacement)->get(route('conversations.safety', $this->thread))->assertForbidden();
    }

    public function test_retargeting_a_report_clears_the_private_conversation_evidence(): void
    {
        $message = $this->thread->createMessageFor($this->seller->id, 'PRIVATE SNAPSHOT');
        $report = Report::fileConversationFor($this->buyer, $this->thread, $message->id, 'spam', null);
        $report->resolve('Resolved by staff');
        $this->assertSame('PRIVATE SNAPSHOT', $report->fresh()->conversation_evidence);
        $report->forceFill(['subject_id' => $this->account()->id])->save();
        $this->assertNull($report->fresh()->conversation_evidence);
        $this->assertNull($report->fresh()->conversation_id);
    }

    public function test_inactive_accounts_and_direct_nonparticipant_message_creation_are_rejected(): void
    {
        $this->buyer->forceFill(['status' => 'suspended'])->save();
        $this->actingAs($this->buyer)->post(route('conversations.block', $this->thread), ['blocked' => '1', 'confirmed' => '1'])->assertForbidden();
        $this->postJson(route('conversations.messages.send', $this->thread), ['message' => 'Suspended'])->assertForbidden();
        $this->post(route('conversations.report', $this->thread), ['reason' => 'spam', 'confirmed' => '1'])->assertForbidden();
        $this->expectException(HttpException::class);
        $this->thread->createMessageFor($this->account()->id, 'Not a participant');
    }
}
