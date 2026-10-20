<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Category\Models\Category;
use Modules\Conversation\App\Models\Conversation;
use Modules\Conversation\App\Models\ConversationMessage;
use Modules\Listing\Models\Listing;
use Modules\User\App\Models\User;
use Tests\TestCase;

final class SellerResponseTest extends TestCase
{
    private User $seller;

    private array $buyers;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(now()->startOfSecond());
        $this->seller = $this->account();
        $this->buyers = [$this->account(), $this->account(), $this->account()];
    }

    protected function tearDown(): void
    {
        $this->travelBack();
        parent::tearDown();
    }

    private function account(): User
    {
        return User::create(['name' => 'Response fixture', 'email' => Str::uuid().'@example.test', 'password' => 'fixture-password', 'status' => 'active']);
    }

    private function thread(int $number, ?int $seconds = 600): Conversation
    {
        $listing = Listing::unguarded(fn () => Listing::create(['title' => 'Response listing', 'description' => 'Private fixture', 'slug' => (string) Str::uuid(), 'category_id' => Category::firstOrFail()->id, 'user_id' => $this->seller->id, 'status' => 'active', 'price' => 100, 'currency' => 'IRR']));
        $thread = Conversation::unguarded(fn () => Conversation::create(['listing_id' => $listing->id, 'seller_id' => $this->seller->id, 'buyer_id' => $this->buyers[$number % 3]->id, 'created_at' => now()->subDays(2)]));
        $time = now()->subDay();
        $this->message($thread, $thread->buyer_id, $time);
        if ($seconds !== null) {
            $this->message($thread, $this->seller->id, $time->copy()->addSeconds($seconds));
        }

        return $thread;
    }

    private function message(Conversation $thread, int $sender, $time): ConversationMessage
    {
        return ConversationMessage::unguarded(fn () => ConversationMessage::create(['conversation_id' => $thread->id, 'sender_id' => $sender, 'body' => 'PRIVATE RESPONSE BODY', 'created_at' => $time, 'updated_at' => $time]));
    }

    public function test_median_uses_first_reply_per_thread_and_rate_includes_unanswered(): void
    {
        foreach ([60, 120, 600, 3600, 7200] as $i => $seconds) {
            $thread = $this->thread($i, $seconds);
            $this->message($thread, $this->seller->id, now()->subHour());
        }
        $this->thread(5, null);
        $summary = Conversation::sellerResponseSummary($this->seller->id);
        $this->assertSame(['seconds' => 600, 'answered' => 5, 'total' => 6, 'rate' => 83], $summary);
        $response = $this->get(route('sellers.show', $this->seller));
        $response->assertOk()->assertViewHas('responseSummary', $summary)->assertDontSee('PRIVATE RESPONSE BODY')->assertDontSee($this->buyers[0]->email);
        $this->assertSame('About 10 minutes', Conversation::sellerResponseLabel($summary));
    }

    public function test_small_samples_or_single_buyer_do_not_get_a_time_claim(): void
    {
        for ($i = 0; $i < 4; $i++) {
            $this->thread($i);
        }
        $this->assertNull(Conversation::sellerResponseSummary($this->seller->id));
        $this->thread(4, null);
        $this->assertNull(Conversation::sellerResponseSummary($this->seller->id));
        $this->thread(5);
        $this->assertNotNull(Conversation::sellerResponseSummary($this->seller->id));
        Conversation::where('seller_id', $this->seller->id)->update(['buyer_id' => $this->buyers[0]->id]);
        $this->assertNull(Conversation::sellerResponseSummary($this->seller->id));
    }

    public function test_deleted_empty_old_self_and_future_threads_do_not_affect_the_sample(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->thread($i);
        }
        $old = $this->thread(6, 0);
        $old->forceFill(['created_at' => now()->subDays(91)])->save();
        $future = $this->thread(7, 0);
        $future->forceFill(['created_at' => now()->addDay()])->save();
        $self = $this->thread(8, 0);
        $self->forceFill(['buyer_id' => $this->seller->id])->save();
        $deleted = $this->thread(9, 0);
        $deleted->delete();
        $empty = $this->thread(10, null);
        $empty->messages()->delete();
        $this->assertSame(5, Conversation::sellerResponseSummary($this->seller->id)['total']);
        $this->assertSame(600, Conversation::sellerResponseSummary($this->seller->id)['seconds']);
    }

    public function test_same_second_order_prior_seller_and_third_party_messages_are_handled(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $thread = $this->thread($i, null);
            $this->message($thread, $this->seller->id, now()->subDay()->subSecond());
            $this->message($thread, $this->account()->id, now()->subDay());
            $this->message($thread, $this->seller->id, now()->subDay());
        }
        $this->assertSame(0, Conversation::sellerResponseSummary($this->seller->id)['seconds']);
        ConversationMessage::where('sender_id', $this->seller->id)->where('created_at', '>=', now()->subDay())->delete();
        $this->assertNull(Conversation::sellerResponseSummary($this->seller->id));
    }

    public function test_inactive_and_deleted_seller_do_not_get_public_statistics(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->thread($i);
        }
        $this->seller->forceFill(['status' => 'suspended'])->save();
        $this->assertNull(Conversation::sellerResponseSummary($this->seller->id));
        $this->seller->delete();
        $this->assertNull(Conversation::sellerResponseSummary($this->seller->id));
    }

    public function test_even_sample_median_and_recent_hundred_thread_limit(): void
    {
        for ($i = 0; $i < 6; $i++) {
            $this->thread($i, ($i + 1) * 60);
        }
        $this->assertSame(210, Conversation::sellerResponseSummary($this->seller->id)['seconds']);
        for ($i = 6; $i < 105; $i++) {
            $this->thread($i, 600);
        }
        $summary = Conversation::sellerResponseSummary($this->seller->id);
        $this->assertSame(100, $summary['total']);
        $this->assertSame(100, $summary['answered']);
        $this->assertSame(600, $summary['seconds']);
    }

    public function test_queries_are_bounded_and_do_not_select_message_bodies(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->thread($i);
        }
        DB::enableQueryLog();
        DB::flushQueryLog();
        $this->assertNotNull(Conversation::sellerResponseSummary($this->seller->id));
        $queries = DB::getQueryLog();
        DB::disableQueryLog();
        $this->assertCount(4, $queries);
        foreach ($queries as $query) {
            $this->assertStringNotContainsString('body', $query['query']);
            $this->assertStringNotContainsString('join', strtolower($query['query']));
        }
        $this->assertStringContainsString('limit 100', strtolower($queries[1]['query']));
    }
}
