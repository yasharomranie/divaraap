<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Support\Str;
use Modules\Category\Models\Category;
use Modules\Listing\Models\Listing;
use Modules\User\App\Models\User;
use Tests\TestCase;

final class ListingBulkTest extends TestCase
{
    protected function tearDown(): void
    {
        $this->travelBack();
        parent::tearDown();
    }

    private function owner(): User
    {
        return User::where('email', 'a@a.com')->firstOrFail();
    }

    private function listing(string $status = 'active', ?User $owner = null): Listing
    {
        return Listing::unguarded(fn () => Listing::create(['title' => 'Bulk fixture '.Str::random(8), 'description' => 'Bulk fixture', 'slug' => (string) Str::uuid(), 'price' => 1000, 'currency' => 'IRR', 'status' => $status, 'user_id' => ($owner ?? $this->owner())->id, 'category_id' => Category::firstOrFail()->id, 'expires_at' => now()->addDays(5)]))->fresh();
    }

    private function ticket(array $listings, string $action): string
    {
        $response = $this->actingAs($this->owner())->post(route('panel.listings.bulk.preview'), ['action' => $action, 'ids' => array_map(fn ($listing) => $listing->id, $listings)]);
        $response->assertOk()->assertHeader('Cache-Control', 'no-store, private')->assertViewHas('ticket');

        return $response->viewData('ticket');
    }

    public function test_preview_does_not_mutate_and_confirmed_pause_resume_is_atomic(): void
    {
        $one = $this->listing();
        $two = $this->listing();
        $expiry = $one->expires_at->toIso8601String();
        $ticket = $this->ticket([$one, $two], 'pause');
        $this->assertSame('active', $one->fresh()->statusValue());
        $this->post(route('panel.listings.bulk.execute'), ['ticket' => $ticket, 'confirmed' => '1'])->assertRedirect(route('panel.listings.index'))->assertSessionHas('success');
        $this->assertSame('paused', $one->fresh()->statusValue());
        $this->assertSame('paused', $two->fresh()->statusValue());
        $this->assertSame($expiry, $one->fresh()->expires_at->toIso8601String());
        $this->post(route('panel.listings.bulk.execute'), ['ticket' => $ticket, 'confirmed' => '1'])->assertSessionHasErrors('selection');
        $ticket = $this->ticket([$one, $two], 'resume');
        $this->post(route('panel.listings.bulk.execute'), ['ticket' => $ticket, 'confirmed' => '1'])->assertRedirect();
        $this->assertSame('active', $one->fresh()->statusValue());
        $this->assertSame('active', $two->fresh()->statusValue());
    }

    public function test_sold_cancels_schedule_and_delete_soft_deletes_only_selected_listings(): void
    {
        $one = $this->listing();
        $two = $this->listing();
        $untouched = $this->listing();
        $two->setScheduleFor($this->owner(), now()->addDay(), $two->scheduleVersion());
        $ticket = $this->ticket([$one, $two], 'sold');
        $this->post(route('panel.listings.bulk.execute'), ['ticket' => $ticket, 'confirmed' => '1'])->assertRedirect();
        $this->assertSame('sold', $one->fresh()->statusValue());
        $this->assertSame('sold', $two->fresh()->statusValue());
        $this->assertNull($two->fresh()->publish_at);
        $ticket = $this->ticket([$one, $two], 'delete');
        $this->post(route('panel.listings.bulk.execute'), ['ticket' => $ticket, 'confirmed' => '1'])->assertRedirect();
        $this->assertSoftDeleted('listings', ['id' => $one->id]);
        $this->assertSoftDeleted('listings', ['id' => $two->id]);
        $this->assertNotNull($untouched->fresh());
        $this->assertSame('active', $untouched->fresh()->statusValue());
    }

    public function test_foreign_missing_and_deleted_ids_never_change_owned_listings(): void
    {
        $one = $this->listing();
        $other = User::whereKeyNot($this->owner()->id)->firstOrFail();
        $foreign = $this->listing('active', $other);
        $this->actingAs($this->owner())->post(route('panel.listings.bulk.preview'), ['action' => 'delete', 'ids' => [$one->id, $foreign->id]])->assertNotFound();
        $this->post(route('panel.listings.bulk.preview'), ['action' => 'delete', 'ids' => [$one->id, 999999999]])->assertNotFound();
        $ticket = $this->ticket([$one], 'delete');
        $this->actingAs($other)->post(route('panel.listings.bulk.execute'), ['ticket' => $ticket, 'confirmed' => '1'])->assertForbidden();
        $this->assertNotNull($one->fresh());
        $this->assertNotNull($foreign->fresh());
    }

    public function test_changed_listing_aborts_entire_confirmed_batch_and_keeps_other_statuses(): void
    {
        $one = $this->listing();
        $two = $this->listing();
        $ticket = $this->ticket([$one, $two], 'pause');
        $two->forceFill(['title' => 'Changed after review'])->save();
        $this->post(route('panel.listings.bulk.execute'), ['ticket' => $ticket, 'confirmed' => '1'])->assertRedirect(route('panel.listings.index'))->assertSessionHasErrors('selection');
        $this->assertSame('active', $one->fresh()->statusValue());
        $this->assertSame('active', $two->fresh()->statusValue());
        $ticket = $this->ticket([$one, $two], 'delete');
        $two->delete();
        $this->post(route('panel.listings.bulk.execute'), ['ticket' => $ticket, 'confirmed' => '1'])->assertNotFound();
        $this->assertNotNull($one->fresh());
    }

    public function test_ineligible_expired_pending_and_scheduled_cannot_bypass_normal_workflow(): void
    {
        $one = $this->listing();
        $pending = $this->listing('pending');
        $paused = $this->listing('paused');
        $paused->forceFill(['expires_at' => now()->subMinute()])->save();
        $this->actingAs($this->owner())->post(route('panel.listings.bulk.preview'), ['action' => 'pause', 'ids' => [$one->id, $pending->id]])->assertSessionHasErrors('action');
        $this->post(route('panel.listings.bulk.preview'), ['action' => 'sold', 'ids' => [$pending->id]])->assertSessionHasErrors('action');
        $this->post(route('panel.listings.bulk.preview'), ['action' => 'resume', 'ids' => [$paused->id]])->assertSessionHasErrors('action');
        $one->setScheduleFor($this->owner(), now()->addDay(), $one->scheduleVersion());
        $this->post(route('panel.listings.bulk.preview'), ['action' => 'resume', 'ids' => [$one->id]])->assertSessionHasErrors('action');
        $this->assertSame('scheduled', $one->fresh()->statusValue());
        $this->assertSame('pending', $pending->fresh()->statusValue());
    }

    public function test_tampered_expired_unconfirmed_and_inactive_owner_requests_are_rejected(): void
    {
        $one = $this->listing();
        $ticket = $this->ticket([$one], 'pause');
        $this->post(route('panel.listings.bulk.execute'), ['ticket' => $ticket])->assertSessionHasErrors('confirmed');
        $this->post(route('panel.listings.bulk.execute'), ['ticket' => 'invalid', 'confirmed' => '1'])->assertSessionHasErrors('selection');
        $this->travel(11)->minutes();
        $this->post(route('panel.listings.bulk.execute'), ['ticket' => $ticket, 'confirmed' => '1'])->assertSessionHasErrors('selection');
        $this->travelBack();
        $this->owner()->forceFill(['status' => 'suspended'])->save();
        $this->post(route('panel.listings.bulk.execute'), ['ticket' => $ticket, 'confirmed' => '1'])->assertForbidden();
        $this->assertSame('active', $one->fresh()->statusValue());
    }

    public function test_input_bounds_duplicates_and_authentication(): void
    {
        $one = $this->listing();
        $this->post(route('panel.listings.bulk.preview'), ['ids' => [$one->id], 'action' => 'pause'])->assertRedirect(route('login'));
        $this->actingAs($this->owner());
        foreach ([[], [$one->id, $one->id], range(1, 51), ['bad'], [[1]], [str_repeat('9', 80)]] as $ids) {
            $this->post(route('panel.listings.bulk.preview'), ['ids' => $ids, 'action' => 'pause'])->assertSessionHasErrors();
        }
        $this->post(route('panel.listings.bulk.preview'), ['ids' => [$one->id], 'action' => 'activate'])->assertSessionHasErrors('action');
    }
}
