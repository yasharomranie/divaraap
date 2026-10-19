<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use Modules\Conversation\App\Models\Conversation;
use Modules\Conversation\App\Models\ConversationMessage;
use Modules\Conversation\App\Models\SavedReply;
use Modules\User\App\Models\User;
use Tests\TestCase;

final class SavedReplyTest extends TestCase
{
    private function user(): User
    {
        return User::where('email', 'a@a.com')->firstOrFail();
    }

    private function payload(array $overrides = []): array
    {
        return array_replace(['title' => 'زمان بازدید', 'body' => "سلام\nبرای هماهنگی زمان بازدید پیام بدهید.", 'position' => 10], $overrides);
    }

    public function test_guest_cannot_read_or_write_private_replies(): void
    {
        $this->get(route('panel.saved-replies.index'))->assertRedirect();
        $this->post(route('panel.saved-replies.store'), $this->payload())->assertRedirect();
        $this->assertSame(0, SavedReply::count());
    }

    public function test_create_update_order_and_soft_delete_are_owner_scoped_and_private(): void
    {
        $this->actingAs($this->user());
        $this->post(route('panel.saved-replies.store'), $this->payload())->assertRedirect(route('panel.saved-replies.index'));
        $reply = SavedReply::firstOrFail();
        $this->assertSame($this->user()->id, $reply->user_id);
        $this->assertSame($this->payload()['body'], $reply->body);
        $raw = DB::table('saved_replies')->where('id', $reply->id)->value('body');
        $this->assertStringNotContainsString('هماهنگی', $raw);
        $this->get(route('panel.saved-replies.index'))->assertOk()->assertSee('زمان بازدید')->assertHeader('Cache-Control', 'no-store, private');
        $this->put(route('panel.saved-replies.update', $reply->id), $this->payload(['title' => 'پاسخ جدید', 'position' => 2]) + ['version' => 1])->assertRedirect();
        $this->assertSame(2, $reply->fresh()->version);
        $another = SavedReply::storeFor($this->user(), $this->payload(['position' => 0]));
        $this->assertSame($another->id, SavedReply::forOwner($this->user())->first()->id);
        $this->delete(route('panel.saved-replies.destroy', $reply->id), ['version' => 2])->assertRedirect();
        $this->assertSoftDeleted('saved_replies', ['id' => $reply->id]);
        $this->assertSame('', SavedReply::withTrashed()->findOrFail($reply->id)->body);
        $this->assertSame('', SavedReply::withTrashed()->findOrFail($reply->id)->title);
    }

    public function test_other_accounts_cannot_see_modify_or_delete_a_reply(): void
    {
        $reply = SavedReply::storeFor($this->user(), $this->payload());
        $other = User::whereKeyNot($this->user()->id)->firstOrFail();
        $this->actingAs($other);
        $this->get(route('panel.saved-replies.index'))->assertOk()->assertDontSee('زمان بازدید');
        $this->put(route('panel.saved-replies.update', $reply->id), $this->payload() + ['version' => 1])->assertNotFound();
        $this->delete(route('panel.saved-replies.destroy', $reply->id), ['version' => 1])->assertNotFound();
        $this->assertNotNull($reply->fresh());
        $this->get(route('panel.inbox.index'))->assertDontSee('data-saved-reply=', false);
    }

    public function test_stale_versions_cannot_overwrite_or_delete_newer_content(): void
    {
        $reply = SavedReply::storeFor($this->user(), $this->payload());
        $this->actingAs($this->user());
        $this->put(route('panel.saved-replies.update', $reply->id), $this->payload(['body' => 'نسخه جدید']) + ['version' => 1])->assertRedirect();
        $this->put(route('panel.saved-replies.update', $reply->id), $this->payload(['body' => 'نسخه قدیمی']) + ['version' => 1])->assertSessionHasErrors('reply');
        $this->delete(route('panel.saved-replies.destroy', $reply->id), ['version' => 1])->assertSessionHasErrors('reply');
        $this->assertSame('نسخه جدید', $reply->fresh()->body);
    }

    public function test_validation_trims_bounds_and_rejects_invalid_types(): void
    {
        $this->actingAs($this->user());
        foreach ([['title' => '  '], ['body' => '  '], ['title' => ['bad']], ['body' => ['bad']], ['body' => str_repeat('آ', 2001)], ['title' => str_repeat('آ', 81)], ['position' => -1], ['position' => 10000]] as $invalid) {
            $this->post(route('panel.saved-replies.store'), $this->payload($invalid))->assertSessionHasErrors(array_key_first($invalid));
            $this->get(route('panel.saved-replies.index'))->assertOk();
        }
        $this->post(route('panel.saved-replies.store'), $this->payload(['title' => '  نام  ', 'body' => '  متن  ']))->assertRedirect();
        $reply = SavedReply::firstOrFail();
        $this->assertSame('نام', $reply->title);
        $this->assertSame('متن', $reply->body);
        $this->get('/panel/saved-replies/999999999999999999999')->assertNotFound();
    }

    public function test_capacity_is_bounded_and_deleted_reply_frees_a_slot(): void
    {
        for ($i = 0; $i < SavedReply::LIMIT; $i++) {
            SavedReply::storeFor($this->user(), $this->payload(['title' => 'پاسخ '.$i]));
        }
        $this->actingAs($this->user());
        $this->post(route('panel.saved-replies.store'), $this->payload())->assertSessionHasErrors('title');
        $reply = SavedReply::firstOrFail();
        $this->delete(route('panel.saved-replies.destroy', $reply->id), ['version' => 1])->assertRedirect();
        $this->post(route('panel.saved-replies.store'), $this->payload())->assertSessionDoesntHaveErrors();
        $this->assertCount(SavedReply::LIMIT, SavedReply::forOwner($this->user()));
    }

    public function test_inactive_user_cannot_write(): void
    {
        $user = $this->user();
        $user->forceFill(['status' => 'suspended'])->saveQuietly();
        $this->actingAs($user)->post(route('panel.saved-replies.store'), $this->payload())->assertForbidden();
        $this->assertSame(0, SavedReply::count());
    }

    public function test_picker_escapes_text_and_rendering_does_not_send_a_message(): void
    {
        $conversation = Conversation::where('seller_id', $this->user()->id)->orWhere('buyer_id', $this->user()->id)->firstOrFail();
        SavedReply::storeFor($this->user(), $this->payload(['body' => '<script>alert("private")</script>']));
        $before = ConversationMessage::count();
        $this->actingAs($this->user())->get(route('panel.inbox.index', ['conversation' => $conversation->id]))->assertOk()->assertSee('data-saved-reply=', false)->assertDontSee('<script>alert("private")</script>', false)->assertSee('&lt;script&gt;', false);
        $this->assertSame($before, ConversationMessage::count());
    }
}
