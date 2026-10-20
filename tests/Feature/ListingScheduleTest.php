<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Modules\Category\Models\Category;
use Modules\Favorite\App\Models\BuyerPublication;
use Modules\Listing\Models\Listing;
use Modules\User\App\Models\User;
use Tests\TestCase;

final class ListingScheduleTest extends TestCase
{
    protected function tearDown(): void
    {
        $this->travelBack();
        parent::tearDown();
    }

    private function user(): User
    {
        return User::where('email', 'a@a.com')->firstOrFail();
    }

    private function listing(string $status = 'active'): Listing
    {
        return Listing::unguarded(fn () => Listing::create(['title' => 'آزمون انتشار زمان‌دار', 'description' => 'توضیح آزمایشی', 'slug' => (string) Str::uuid(), 'price' => 1000, 'currency' => 'IRR', 'status' => $status, 'user_id' => $this->user()->id, 'category_id' => Category::where('name', 'Phones')->firstOrFail()->id]))->fresh();
    }

    public function test_future_approved_listing_is_hidden_and_published_once_at_due_time(): void
    {
        $listing = $this->listing();
        $time = now()->addHours(2);
        $listing->setScheduleFor($this->user(), $time, $listing->scheduleVersion());
        $this->assertSame('scheduled', $listing->statusValue());
        $this->assertFalse(Listing::active()->whereKey($listing->id)->exists());
        $this->get(route('listings.show', $listing))->assertNotFound();
        $this->assertSame(0, Listing::publishDue());
        $this->travelTo($time->copy()->addSecond());
        $this->assertSame(1, Listing::publishDue());
        $this->assertSame(0, Listing::publishDue());
        $this->assertSame('active', $listing->fresh()->statusValue());
        $this->assertNull($listing->fresh()->publish_at);
        $this->assertTrue($listing->fresh()->expires_at->greaterThan(now()->addDays(29)));
        $this->get(route('listings.show', $listing))->assertOk();
    }

    public function test_pending_moderation_waits_for_approval_even_after_time_arrives(): void
    {
        $listing = $this->listing('pending');
        $time = now()->addHour();
        $listing->setScheduleFor($this->user(), $time, $listing->scheduleVersion());
        $this->assertSame('pending', $listing->statusValue());
        $this->travelTo($time->copy()->addMinute());
        $this->assertSame(0, Listing::publishDue());
        $this->assertSame('pending', $listing->fresh()->statusValue());
        $listing->moderate(true);
        $this->assertSame('active', $listing->statusValue());
        $this->assertNull($listing->publish_at);
        $this->assertTrue($listing->expires_at->greaterThan(now()->addDays(29)));
    }

    public function test_approval_from_partial_moderation_queue_does_not_publish_early(): void
    {
        $listing = $this->listing('pending');
        $time = now()->addDay();
        $listing->setScheduleFor($this->user(), $time, $listing->scheduleVersion());
        $queueRecord = Listing::moderationQueue()->firstWhere('id', $listing->id);
        $this->assertNotNull($queueRecord);
        $queueRecord->moderate(true);
        $this->assertSame('scheduled', $listing->fresh()->statusValue());
        $this->assertFalse(BuyerPublication::where('listing_id', $listing->id)->exists());
        $this->travelTo($time->copy()->addMinute());
        $this->assertSame(1, Listing::publishDue());
        $this->assertTrue(BuyerPublication::where('listing_id', $listing->id)->exists());
    }

    public function test_first_safe_web_request_processes_due_listings_without_shell_or_cron(): void
    {
        $listing = $this->listing();
        $time = now()->addMinute();
        $listing->setScheduleFor($this->user(), $time, $listing->scheduleVersion());
        $this->travelTo($time->copy()->addMinute());
        $this->get(route('listings.show', $listing))->assertOk();
        $this->assertSame('active', $listing->fresh()->statusValue());
    }

    public function test_tehran_input_converts_to_app_timezone_and_rejects_invalid_dates(): void
    {
        $this->travelTo(Carbon::parse('2026-10-20 18:00:00', 'UTC'));
        $listing = $this->listing();
        $this->actingAs($this->user());
        $this->post(route('panel.listings.schedule.save', $listing), ['publish_at' => '2026-10-21T01:00', 'version' => $listing->scheduleVersion()])->assertRedirect();
        $this->assertSame('2026-10-20 21:30', $listing->fresh()->publish_at->format('Y-m-d H:i'));
        $listing = $listing->fresh();
        $listing->setScheduleFor($this->user(), Carbon::parse('2026-10-21 02:00', 'Asia/Tehran'), $listing->scheduleVersion());
        $this->assertSame('2026-10-20 22:30', $listing->fresh()->publish_at->format('Y-m-d H:i'));
        foreach (['2026-02-30T20:00', '2026-10-20T01:00', '2028-10-20T01:00', 'invalid'] as $date) {
            $this->post(route('panel.listings.schedule.save', $listing), ['publish_at' => $date, 'version' => $listing->fresh()->scheduleVersion()])->assertSessionHasErrors('publish_at');
        }
    }

    public function test_cancel_preserves_approval_and_stale_edits_cannot_change_new_schedule(): void
    {
        $listing = $this->listing();
        $firstVersion = $listing->scheduleVersion();
        $listing->setScheduleFor($this->user(), now()->addDay(), $firstVersion);
        $this->actingAs($this->user())->post(route('panel.listings.schedule.save', $listing), ['publish_at' => now()->addDays(2)->timezone('Asia/Tehran')->format('Y-m-d\TH:i'), 'version' => $firstVersion])->assertSessionHasErrors('publish_at');
        $this->delete(route('panel.listings.schedule.cancel', $listing), ['version' => $listing->scheduleVersion()])->assertRedirect();
        $this->assertSame('paused', $listing->fresh()->statusValue());
        $this->assertNull($listing->fresh()->publish_at);
        $pending = $this->listing('pending');
        $pending->setScheduleFor($this->user(), now()->addDay(), $pending->scheduleVersion());
        $pending->setScheduleFor($this->user(), null, $pending->scheduleVersion());
        $this->assertSame('pending', $pending->statusValue());
    }

    public function test_editor_renewal_rejection_and_sold_state_cannot_publish_early(): void
    {
        $listing = $this->listing();
        $listing->setScheduleFor($this->user(), now()->addDay(), $listing->scheduleVersion());
        $listing->updateFromPanel(['status' => 'active', 'title' => 'ویرایش امن']);
        $this->assertSame('scheduled', $listing->statusValue());
        $listing->republish();
        $this->assertSame('scheduled', $listing->statusValue());
        $listing->markAsSold();
        $this->assertSame('sold', $listing->statusValue());
        $this->assertNull($listing->publish_at);
        $pending = $this->listing('pending');
        $pending->setScheduleFor($this->user(), now()->addDay(), $pending->scheduleVersion());
        $pending->moderate(false);
        $this->assertSame('rejected', $pending->statusValue());
        $this->assertNull($pending->publish_at);
    }

    public function test_inactive_owner_is_not_published_and_command_is_available(): void
    {
        $listing = $this->listing();
        $listing->setScheduleFor($this->user(), now()->addMinute(), $listing->scheduleVersion());
        $this->user()->forceFill(['status' => 'suspended'])->saveQuietly();
        $this->travel(2)->minutes();
        $this->artisan('listings:publish-due')->assertSuccessful();
        $this->assertSame('paused', $listing->fresh()->statusValue());
        $this->assertNull($listing->fresh()->publish_at);
    }

    public function test_owner_authorization_and_public_contact_guards_apply(): void
    {
        $listing = $this->listing();
        $this->get(route('panel.listings.schedule', $listing))->assertRedirect();
        $other = User::whereKeyNot($this->user()->id)->firstOrFail();
        $this->actingAs($other)->get(route('panel.listings.schedule', $listing))->assertForbidden();
        $this->post(route('panel.listings.schedule.save', $listing), ['publish_at' => now()->addDay()->format('Y-m-d\TH:i'), 'version' => $listing->scheduleVersion()])->assertForbidden();
        $listing->setScheduleFor($this->user(), now()->addDay(), $listing->scheduleVersion());
        $this->get(route('listings.contact', $listing))->assertForbidden();
        $this->post(route('conversations.start', $listing), ['message' => 'سلام'])->assertNotFound();
        $this->post(route('offers.store', $listing->id), ['amount' => 1000])->assertNotFound();
        $this->actingAs($this->user())->get(route('listings.show', $listing))->assertOk()->assertHeader('Cache-Control', 'no-store, private');
        $this->post('/panel/my-listings/99999999999999999999999/schedule')->assertNotFound();
    }
}
