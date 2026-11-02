<?php

declare(strict_types=1);

namespace Tests\Feature;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Event;
use Illuminate\Validation\ValidationException;
use Modules\Category\Models\Category;
use Modules\Conversation\App\Events\InboxMessageCreated;
use Modules\Conversation\App\Models\Conversation;
use Modules\Conversation\App\Models\ConversationMessage;
use Modules\Listing\Models\Listing;
use Modules\User\App\Models\Profile;
use Modules\User\App\Models\User;
use Spatie\Activitylog\Models\Activity;
use Tests\TestCase;

final class SellerAvailabilityTest extends TestCase
{
    private User $seller;

    private User $buyer;

    private Listing $listing;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seller = User::factory()->create(['status' => 'active']);
        $this->buyer = User::factory()->create(['status' => 'active']);
        $this->seller->syncRoles([]);
        $this->buyer->syncRoles([]);
        $this->listing = Listing::unguarded(fn () => Listing::create(['title' => 'Availability sample listing', 'description' => 'Availability fixture', 'slug' => bin2hex(random_bytes(12)), 'status' => 'active', 'user_id' => $this->seller->id, 'category_id' => Category::where('name', 'Phones')->firstOrFail()->id, 'price' => 100000, 'currency' => 'IRR']));
    }

    private function data(array $overrides = []): array
    {
        return array_replace(['enabled' => '1', 'until' => now()->addHours(2)->setTimezone('Asia/Tehran')->format('Y-m-d\TH:i'), 'reason' => 'delayed', 'revision' => Profile::availabilityFor($this->seller)['revision'], 'public_consent' => 1], $overrides);
    }

    private function enable(): void
    {
        Profile::saveAvailabilityFor($this->seller, $this->data());
    }

    public function test_settings_require_login_active_owner_and_explicit_consent_and_cannot_target_another_user(): void
    {
        $this->get(route('seller-availability.edit'))->assertRedirect(route('login'));
        $this->put(route('seller-availability.update'))->assertRedirect(route('login'));
        $this->actingAs($this->seller);
        $this->get(route('seller-availability.edit'))->assertOk()->assertHeader('Cache-Control', 'no-store, private')->assertHeader('X-Robots-Tag', 'noindex, nofollow');
        $this->putJson(route('seller-availability.update'), $this->data(['public_consent' => 0]))->assertUnprocessable()->assertJsonValidationErrors('public_consent');
        $this->put(route('seller-availability.update'), $this->data(['user_id' => $this->buyer->id, 'shop_enabled' => true, 'is_verified' => true]))->assertRedirect(route('seller-availability.edit'));
        $this->assertNotNull(Profile::publicAvailability($this->seller->id));
        $this->assertNull(Profile::publicAvailability($this->buyer->id));
        $this->assertFalse((bool) Profile::detailsForUser($this->seller)->is_verified);
    }

    public function test_invalid_dates_reasons_and_revision_are_rejected_without_publishing(): void
    {
        $this->actingAs($this->seller);
        foreach ([['until' => ''], ['until' => 'not-a-date'], ['until' => '2026-02-30T12:00'], ['until' => now()->subHour()->setTimezone('Asia/Tehran')->format('Y-m-d\TH:i')], ['until' => now()->addDays(31)->setTimezone('Asia/Tehran')->format('Y-m-d\TH:i')], ['reason' => '<script>bad()</script>'], ['revision' => -1]] as $invalid) {
            $this->putJson(route('seller-availability.update'), $this->data($invalid))->assertUnprocessable();
        }
        $this->assertNull(Profile::publicAvailability($this->seller->id));
    }

    public function test_tehran_deadline_is_stored_correctly_and_expires_without_scheduler_or_data_mutation(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-11-02 08:00:00', 'UTC'));
        Profile::saveAvailabilityFor($this->seller, $this->data(['until' => '2026-11-02T12:30']));
        $profile = Profile::detailsForUser($this->seller);
        $this->assertSame('2026-11-02 09:00', $profile->away_until->utc()->format('Y-m-d H:i'));
        $this->assertSame('2026-11-02T12:30', Profile::availabilityFor($this->seller)['until']);
        $this->assertSame('12:30', Profile::publicAvailability($this->seller->id)['until']->format('H:i'));
        $this->travelTo(CarbonImmutable::parse('2026-11-02 09:00:00', 'UTC'));
        $this->assertNull(Profile::publicAvailability($this->seller->id));
        $this->assertFalse(Profile::availabilityFor($this->seller)['active']);
        $this->assertTrue(Profile::detailsForUser($this->seller)->away_enabled);
        $this->assertSame(1, Profile::detailsForUser($this->seller)->away_revision);
    }

    public function test_disabling_clears_public_fields_without_requiring_consent_or_a_new_date(): void
    {
        $this->enable();
        $this->actingAs($this->seller)->put(route('seller-availability.update'), ['enabled' => 0, 'until' => 'invalid', 'reason' => 'invalid', 'revision' => 1])->assertRedirect();
        $profile = Profile::detailsForUser($this->seller);
        $this->assertFalse($profile->away_enabled);
        $this->assertNull($profile->away_until);
        $this->assertNull($profile->away_reason);
        $this->assertSame(2, $profile->away_revision);
        $this->assertNull(Profile::publicAvailability($this->seller->id));
    }

    public function test_stale_tab_cannot_replace_or_reenable_current_state(): void
    {
        $data = $this->data();
        Profile::saveAvailabilityFor($this->seller, $data);
        try {
            Profile::saveAvailabilityFor($this->seller, array_replace($data, ['reason' => 'break']));
            $this->fail('Expected stale version rejection.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('revision', $exception->errors());
        }
        $this->assertSame(1, Profile::detailsForUser($this->seller)->away_revision);
        $this->assertSame('delayed', Profile::publicAvailability($this->seller->id)['reason']);
    }

    public function test_suspended_deleted_profiles_and_accounts_do_not_publish_or_allow_settings(): void
    {
        $this->enable();
        $this->seller->update(['status' => 'suspended']);
        $this->assertNull(Profile::publicAvailability($this->seller->id));
        $this->actingAs($this->seller)->get(route('seller-availability.edit'))->assertForbidden();
        $this->putJson(route('seller-availability.update'), ['enabled' => 0, 'revision' => 1])->assertForbidden();
        $this->seller->update(['status' => 'active']);
        Profile::detailsForUser($this->seller)->delete();
        $this->assertNull(Profile::publicAvailability($this->seller->id));
        Profile::saveAvailabilityFor($this->seller, $this->data());
        $this->assertSame(1, Profile::where('user_id', $this->seller->id)->count());
        $this->seller->delete();
        $this->assertNull(Profile::publicAvailability($this->seller->id));
    }

    public function test_notice_appears_on_listing_seller_and_buyer_chat_without_automatic_messages(): void
    {
        $this->enable();
        $this->get(route('listings.show', $this->listing))->assertOk()->assertSee('data-seller-availability', false)->assertHeader('Cache-Control', 'no-store, private');
        $this->get(route('sellers.show', $this->seller))->assertOk()->assertSee('data-seller-availability', false);
        $thread = Conversation::openForListingBuyer($this->listing, $this->buyer->id);
        $this->actingAs($this->buyer)->get(route('panel.inbox.index', ['conversation' => $thread->id]))->assertOk()->assertSee('data-seller-availability', false);
        $this->assertSame(0, ConversationMessage::where('conversation_id', $thread->id)->count());
        $this->actingAs($this->seller)->get(route('panel.inbox.index', ['conversation' => $thread->id]))->assertOk()->assertDontSee('data-seller-availability', false);
        Event::fake([InboxMessageCreated::class]);
        $this->actingAs($this->buyer)->postJson(route('conversations.start', $this->listing), ['message' => 'An actual buyer message'])->assertSuccessful();
        $this->assertSame(1, ConversationMessage::where('conversation_id', $thread->id)->count());
        $this->assertSame('active', $this->listing->fresh()->statusValue());
    }

    public function test_availability_does_not_change_shop_verification_profile_or_listing_fields(): void
    {
        Profile::saveShopFor($this->seller, ['shop_enabled' => true, 'shop_name' => 'Existing shop', 'shop_description' => 'Existing description', 'shop_hours' => '9–18'], Profile::shopVersion($this->seller));
        $shopVersion = Profile::shopVersion($this->seller);
        $verificationVersion = Profile::verificationVersion($this->seller);
        $listing = $this->listing->fresh()->getAttributes();
        Profile::saveAvailabilityFor($this->seller, $this->data());
        $this->assertSame($shopVersion, Profile::shopVersion($this->seller));
        $this->assertSame($verificationVersion, Profile::verificationVersion($this->seller));
        $this->assertSame('Existing shop', Profile::detailsForUser($this->seller)->shop_name);
        $this->assertSame($listing, $this->listing->fresh()->getAttributes());
        $activity = Activity::where('log_name', 'seller-availability')->firstOrFail();
        $this->assertSame(['enabled' => true, 'revision' => 1], $activity->properties->all());
    }

    public function test_public_data_has_only_preset_and_deadline_and_one_query_without_joins(): void
    {
        $this->enable();
        $connection = Profile::resolveConnection();
        $connection->flushQueryLog();
        $connection->enableQueryLog();
        try {
            $notice = Profile::publicAvailability($this->seller->id);
            $queries = $connection->getQueryLog();
            $this->assertCount(1, $queries);
            $this->assertStringNotContainsString(' join ', strtolower($queries[0]['query']));
            $this->assertSame(['until', 'reason'], array_keys($notice));
        } finally {
            $connection->disableQueryLog();
            $connection->flushQueryLog();
        }
    }
}
