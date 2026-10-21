<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Support\PersianLocale;
use Filament\Facades\Filament;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Modules\Admin\Filament\Pages\Operations;
use Modules\Category\Models\Category;
use Modules\Conversation\App\Models\Conversation;
use Modules\Conversation\App\Models\ConversationBlock;
use Modules\Listing\Filament\Admin\Resources\WantedRequestResource;
use Modules\Listing\Filament\Admin\Resources\WantedRequestResource\Pages\ListWantedRequests;
use Modules\Listing\Models\Listing;
use Modules\Listing\Models\WantedRequest;
use Modules\Listing\Models\WantedResponse;
use Modules\Location\Models\City;
use Modules\User\App\Models\User;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

final class WantedRequestTest extends TestCase
{
    private User $buyer;

    private User $seller;

    private User $admin;

    private int $category;

    protected function setUp(): void
    {
        parent::setUp();
        $this->buyer = User::factory()->create(['status' => 'active']);
        $this->seller = User::factory()->create(['status' => 'active']);
        $this->buyer->syncRoles([]);
        $this->seller->syncRoles([]);
        $this->admin = User::where('email', 'a@a.com')->firstOrFail();
        $this->category = Category::where('name', 'Phones')->firstOrFail()->id;
    }

    private function input(array $data = []): array
    {
        return array_replace(['request_key' => (string) Str::uuid(), 'title' => 'گوشی کم‌کارکرد می‌خواهم', 'description' => 'گوشی سالم با جعبه و باتری سالم نیاز دارم.', 'category_id' => $this->category, 'budget' => 20000000], $data);
    }

    private function wanted(array $data = [], bool $publish = true): WantedRequest
    {
        $request = WantedRequest::submit($this->buyer, $this->input($data));
        if ($publish) {
            $this->actingAs($this->admin);
            $request->moderate(true);
        }

        return $request;
    }

    private function listing(array $data = []): Listing
    {
        return Listing::unguarded(fn () => Listing::create(array_replace(['title' => 'گوشی پیشنهادی فروشنده', 'description' => 'گوشی سالم و آماده فروش', 'slug' => (string) Str::uuid(), 'status' => 'active', 'user_id' => $this->seller->id, 'category_id' => $this->category, 'price' => 15000000, 'currency' => 'IRR', 'expires_at' => now()->addDays(7)], $data)));
    }

    private function invalid(callable $action): void
    {
        try {
            $action();
            $this->fail('Expected validation failure.');
        } catch (ValidationException) {
            $this->assertTrue(true);
        }
    }

    private function httpFailure(int $status, callable $action): void
    {
        try {
            $action();
            $this->fail('Expected HTTP failure.');
        } catch (ModelNotFoundException) {
            $this->assertSame(404, $status);
        } catch (HttpException $exception) {
            $this->assertSame($status, $exception->getStatusCode());
        }
    }

    public function test_submission_requires_review_ignores_spoofed_fields_and_replays_once(): void
    {
        $data = $this->input(['user_id' => $this->seller->id, 'state' => 'open', 'revision' => 55, 'expires_at' => now()->addYear()]);
        $this->actingAs($this->buyer)->post(route('wanted.store'), $data)->assertRedirect();
        $request = WantedRequest::where('request_key', $data['request_key'])->firstOrFail();
        $this->assertSame($this->buyer->id, $request->user_id);
        $this->assertSame('pending', $request->state);
        $this->assertSame(1, $request->revision);
        $this->assertSame(30, (int) round(now()->diffInDays($request->expires_at)));
        $this->assertSame($request->id, WantedRequest::submit($this->buyer, $data)->id);
        $this->invalid(fn () => WantedRequest::submit($this->buyer, array_replace($data, ['title' => 'Changed title'])));
        $this->get(route('wanted.show', $request))->assertNotFound();
        $this->get(route('wanted.own', $request))->assertOk()->assertHeader('Cache-Control', 'no-store, private');
        $this->httpFailure(403, fn () => $request->update(['state' => 'open']));
        $this->httpFailure(403, fn () => $request->delete());
    }

    public function test_content_category_city_budget_and_active_account_validation(): void
    {
        foreach ([['title' => '    a    '], ['description' => '    x    '], ['budget' => -1], ['budget' => '1.5'], ['category_id' => 999999999], ['city_id' => 999999999]] as $data) {
            $this->invalid(fn () => WantedRequest::submit($this->buyer, $this->input($data)));
        }
        $services = Category::advertisingServiceCategoryIds();
        if ($services !== []) {
            $this->invalid(fn () => WantedRequest::submit($this->buyer, $this->input(['category_id' => $services[0]])));
        }
        $this->buyer->update(['status' => 'suspended']);
        $this->httpFailure(403, fn () => WantedRequest::submit($this->buyer, $this->input()));
    }

    public function test_public_browse_filters_search_and_escaped_content(): void
    {
        $request = $this->wanted(['title' => 'گوشی کارکرده مدل ۱۲۳ <script>alert(1)</script>']);
        $pending = $this->wanted(['title' => 'Private pending title'], false);
        auth()->logout();
        $this->get(route('wanted.index', ['search' => 'كاركرده 123', 'category_id' => $this->category]))->assertOk()->assertSee(e($request->title), false)->assertSee(PersianLocale::number(20000000))->assertDontSee($pending->title)->assertDontSee('<script>alert(1)</script>', false);
        $this->get(route('wanted.show', $request))->assertOk()->assertDontSee($this->buyer->email)->assertDontSee('reviewed_by');
        $this->get(route('wanted.index', ['category_id' => 999999999]))->assertOk()->assertDontSee(e($request->title), false);
        $this->getJson(route('wanted.index', ['search' => ['invalid']]))->assertUnprocessable();
    }

    public function test_edit_hides_public_copy_resets_review_and_keeps_expiry_and_revisions(): void
    {
        $request = $this->wanted();
        $expiry = $request->expires_at->toISOString();
        $request->updateFor($this->buyer, $this->input(['title' => 'نیاز به گوشی دیگر']), 1);
        $this->assertSame('pending', $request->state);
        $this->assertSame(2, $request->revision);
        $this->assertNull($request->reviewed_at);
        $this->assertSame($expiry, $request->expires_at->toISOString());
        $this->get(route('wanted.show', $request))->assertNotFound();
        $this->httpFailure(409, fn () => $request->updateFor($this->buyer, $this->input(), 1));
        $this->httpFailure(409, fn () => $request->moderate(true, null, 1));
        $request->moderate(true, null, 2);
        $this->assertSame('open', $request->state);
    }

    public function test_owner_isolation_close_found_and_expiry(): void
    {
        $request = $this->wanted();
        $this->actingAs($this->seller)->get(route('wanted.own', $request))->assertNotFound();
        $this->httpFailure(404, fn () => $request->closeFor($this->seller, 'fulfilled', 1));
        $request->closeFor($this->buyer, 'fulfilled', 1);
        $request->closeFor($this->buyer, 'fulfilled', 1);
        $this->assertSame('fulfilled', $request->state);
        $this->get(route('wanted.show', $request))->assertNotFound();
        $this->httpFailure(409, fn () => $request->updateFor($this->buyer, $this->input(), 1));
        $expiring = $this->wanted();
        $this->travel(31)->days();
        $this->get(route('wanted.show', $expiring))->assertNotFound();
        $this->assertSame(__('listing::wanted.expired'), $expiring->stateLabel());
        $this->httpFailure(409, fn () => $expiring->closeFor($this->buyer, 'fulfilled', 1));
        $expiring->closeFor($this->buyer, 'closed', 1);
        $this->assertSame('closed', $expiring->state);
    }

    public function test_suggestion_is_private_owned_live_same_category_and_idempotent(): void
    {
        $request = $this->wanted();
        $listing = $this->listing();
        $this->actingAs($this->seller)->post(route('wanted.suggest', $request), ['listing_id' => $listing->id, 'revision' => 1])->assertRedirect(route('wanted.show', $request));
        $response = WantedResponse::submit($this->seller, $request->id, $listing->id, 1);
        $this->assertSame(1, WantedResponse::where('wanted_request_id', $request->id)->count());
        $this->assertSame($response->id, WantedResponse::submit($this->seller, $request->id, $listing->id, 1)->id);
        $other = $this->listing();
        $this->invalid(fn () => WantedResponse::submit($this->seller, $request->id, $other->id, 1));
        $this->get(route('wanted.show', $request))->assertDontSee($listing->title);
        $this->actingAs($this->buyer)->get(route('wanted.own', $request))->assertOk()->assertSee($listing->title)->assertSee(route('conversations.start', $listing), false);
        $this->httpFailure(403, fn () => $response->delete());
        $this->httpFailure(403, fn () => $response->update(['seller_id' => $this->buyer->id]));
    }

    public function test_invalid_foreign_paused_expired_category_and_self_suggestions_fail(): void
    {
        $request = $this->wanted();
        foreach ([['user_id' => $this->buyer->id], ['status' => 'pending'], ['status' => 'paused'], ['expires_at' => now()->subMinute()], ['category_id' => Category::whereKeyNot($this->category)->firstOrFail()->id]] as $data) {
            $listing = $this->listing($data);
            $this->httpFailure(404, fn () => WantedResponse::submit($this->seller, $request->id, $listing->id, 1));
        }
        $listing = $this->listing();
        $this->httpFailure(422, fn () => WantedResponse::submit($this->buyer, $request->id, $listing->id, 1));
        $this->httpFailure(409, fn () => WantedResponse::submit($this->seller, $request->id, $listing->id, 2));
    }

    public function test_city_compatibility_and_inactive_location_or_category_hide_request(): void
    {
        $city = City::query()->active()->with('country')->firstOrFail();
        $request = $this->wanted(['city_id' => $city->id]);
        $listing = $this->listing(['city' => $city->name, 'country' => $city->country->name]);
        WantedResponse::submit($this->seller, $request->id, $listing->id, 1);
        $otherSeller = User::factory()->create(['status' => 'active']);
        $other = $this->listing(['user_id' => $otherSeller->id, 'city' => 'Elsewhere', 'country' => $city->country->name]);
        $this->httpFailure(404, fn () => WantedResponse::submit($otherSeller, $request->id, $other->id, 1));
        $city->update(['is_active' => false]);
        $this->get(route('wanted.show', $request))->assertNotFound();
        $city->update(['is_active' => true]);
        Category::findOrFail($this->category)->update(['is_active' => false]);
        $this->get(route('wanted.show', $request))->assertNotFound();
    }

    public function test_blocks_and_suspension_prevent_suggestions_and_publication(): void
    {
        $request = $this->wanted();
        $listing = $this->listing();
        ConversationBlock::setFor($this->buyer, $this->seller->id, true);
        $this->invalid(fn () => WantedResponse::submit($this->seller, $request->id, $listing->id, 1));
        $this->assertSame([], $request->suggestionOptions($this->seller));
        ConversationBlock::setFor($this->buyer, $this->seller->id, false);
        $this->buyer->update(['status' => 'suspended']);
        $this->get(route('wanted.show', $request))->assertNotFound();
        $this->httpFailure(404, fn () => WantedResponse::submit($this->seller, $request->id, $listing->id, 1));
    }

    public function test_old_suggestions_do_not_follow_an_edited_request_and_unavailable_listing_hides(): void
    {
        $request = $this->wanted();
        $listing = $this->listing();
        WantedResponse::submit($this->seller, $request->id, $listing->id, 1);
        $listing->forceFill(['status' => 'paused'])->save();
        $this->actingAs($this->buyer)->get(route('wanted.own', $request))->assertSee(__('listing::wanted.unavailable'))->assertDontSee($listing->title);
        $request->updateFor($this->buyer, $this->input(), 1);
        $this->assertSame(0, WantedResponse::forOwner($request, $this->buyer)->total());
        $this->actingAs($this->admin);
        $request->moderate(true);
        $listing->forceFill(['status' => 'active'])->save();
        WantedResponse::submit($this->seller, $request->id, $listing->id, 2);
        $this->assertSame(1, WantedResponse::forOwner($request, $this->buyer)->total());
        $this->assertSame(2, WantedResponse::where('wanted_request_id', $request->id)->count());
    }

    public function test_existing_conversation_is_reused_without_creating_a_parallel_thread(): void
    {
        $request = $this->wanted();
        $listing = $this->listing();
        WantedResponse::submit($this->seller, $request->id, $listing->id, 1);
        $this->actingAs($this->buyer)->post(route('conversations.start', $listing))->assertRedirect();
        $this->post(route('conversations.start', $listing))->assertRedirect();
        $this->assertSame(1, Conversation::where('listing_id', $listing->id)->where('buyer_id', $this->buyer->id)->count());
    }

    public function test_admin_and_moderator_review_via_existing_queue_and_resource_with_revision_guard(): void
    {
        $request = $this->wanted([], false);
        $moderator = User::factory()->create(['status' => 'active']);
        $moderator->assignRole('moderator');
        $this->actingAs($moderator);
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $this->assertTrue(WantedRequestResource::canViewAny());
        Livewire::test(Operations::class)->call('review', 'wanted', $request->id, 'approve', 1)->assertHasNoErrors();
        $this->assertSame('open', $request->fresh()->state);
        Livewire::test(ListWantedRequests::class)->callTableAction('retract', $request, ['note' => 'Please clarify the need.', 'revision' => 1])->assertHasNoTableActionErrors();
        $this->assertSame('rejected', $request->fresh()->state);
        $request = $request->fresh();
        $request->updateFor($this->buyer, $this->input(), 1);
        $this->httpFailure(409, fn () => $request->moderate(true, null, 1));
        Livewire::test(ListWantedRequests::class)->callTableAction('approve', $request, ['revision' => 2])->assertHasNoTableActionErrors();
        $this->assertSame('open', $request->fresh()->state);
        $this->assertFalse(WantedRequestResource::canCreate());
        $this->assertFalse(WantedRequestResource::canEdit($request));
        $this->actingAs($this->seller)->get(WantedRequestResource::getUrl())->assertForbidden();
        $this->httpFailure(403, fn () => $request->moderate(false));
    }

    public function test_rejected_request_edits_cannot_bypass_the_open_request_limit(): void
    {
        $rejected = $this->wanted([], false);
        $this->actingAs($this->admin);
        $rejected->moderate(false);
        for ($i = 0; $i < 20; $i++) {
            WantedRequest::submit($this->buyer, $this->input());
        }
        $this->invalid(fn () => $rejected->updateFor($this->buyer, $this->input(), 1));
        $this->assertSame('rejected', $rejected->fresh()->state);
    }

    public function test_twenty_open_requests_limit_and_closed_slot_release(): void
    {
        for ($i = 0; $i < 20; $i++) {
            WantedRequest::submit($this->buyer, $this->input(['title' => 'Needed item '.$i]));
        }
        $this->invalid(fn () => WantedRequest::submit($this->buyer, $this->input()));
        $first = WantedRequest::where('user_id', $this->buyer->id)->firstOrFail();
        $first->closeFor($this->buyer, 'closed', 1);
        $this->assertSame('pending', WantedRequest::submit($this->buyer, $this->input())->state);
    }
}
