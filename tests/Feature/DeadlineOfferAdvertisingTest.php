<?php

declare(strict_types=1);

namespace Tests\Feature;

use Filament\Facades\Filament;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Modules\Advertising\Filament\Admin\Resources\BannerResource\Pages\EditBanner;
use Modules\Advertising\Models\AdvertisingEvent;
use Modules\Advertising\Models\Banner;
use Modules\Advertising\Models\BannerPlacement;
use Modules\Advertising\Models\Campaign;
use Modules\Advertising\Support\AdvertisingToken;
use Modules\Advertising\Support\BannerRenderer;
use Modules\Category\Models\Category;
use Modules\Listing\Models\Listing;
use Modules\User\App\Models\User;
use Tests\TestCase;

final class DeadlineOfferAdvertisingTest extends TestCase
{
    private User $seller;

    private Listing $listing;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
        $image = UploadedFile::fake()->image('deadline.jpg');
        Storage::disk('public')->put('banners/deadline.jpg', file_get_contents($image->getRealPath()));
        $this->seller = User::factory()->create(['name' => 'PRIVATE OFFER OWNER', 'status' => 'active']);
        $this->listing = Listing::unguarded(fn () => Listing::create(['title' => 'Deadline listing fixture', 'description' => 'Public fixture', 'price' => 100000, 'currency' => 'IRR', 'user_id' => $this->seller->id, 'category_id' => Category::firstOrFail()->id, 'slug' => (string) Str::uuid(), 'status' => 'active']));
    }

    private function card(array $data = []): Banner
    {
        $card = Banner::create(array_replace(['title' => 'Fixed deadline fixture', 'alt' => 'Offer image', 'image' => 'banners/deadline.jpg', 'creative_format' => 'deadline', 'offer_listing_id' => $this->listing->id, 'ends_at' => now()->addHours(2), 'device' => 'both', 'is_active' => true, 'review_status' => 'approved'], $data));
        BannerPlacement::where('key', 'home-top')->firstOrFail()->assignments()->create(['banner_id' => $card->id, 'priority' => 1]);

        return $card;
    }

    private function token(Banner $card): string
    {
        $this->get('/')->assertOk();
        $token = AdvertisingToken::issue($card, 'home-top', 'both', []);
        $this->withCookie(config('session.cookie'), request()->session()->getId());

        return $token;
    }

    public function test_offer_uses_current_public_listing_price_direct_destination_and_fixed_absolute_time(): void
    {
        $card = $this->card(['url' => 'https://unrelated.example']);
        $this->assertSame(route('listings.show', $this->listing), $card->destination());
        $end = $card->effectiveDeadline()->toIso8601String();
        foreach (range(1, 2) as $attempt) {
            $this->get('/')->assertOk()->assertSee('data-ad-deadline="'.$card->id.'"', false)->assertSee('data-deadline="'.$end.'"', false)->assertSee($this->listing->title)->assertSee(__('advertising::deadline.note'))->assertDontSee('PRIVATE OFFER OWNER')->assertDontSee('https://unrelated.example');
        }
        $this->assertSame($end, $card->fresh()->effectiveDeadline()->toIso8601String());
        $this->listing->update(['title' => '<script>deadline()</script>', 'price' => 200000]);
        $this->get('/')->assertOk()->assertSee('&lt;script&gt;deadline()&lt;/script&gt;', false)->assertDontSee('<script>deadline()</script>', false);
        $this->assertSame($this->listing->panelPriceLabel(), $card->fresh()->advertisedDeadlineListing()['price']);
    }

    public function test_end_is_required_future_and_after_start_including_listing_expiry(): void
    {
        foreach ([['ends_at' => null], ['ends_at' => now()], ['ends_at' => now()->subSecond()], ['starts_at' => now()->addHours(3)]] as $data) {
            try {
                $this->card($data);
                $this->fail('Invalid end accepted');
            } catch (ValidationException $exception) {
                $this->assertArrayHasKey('ends_at', $exception->errors());
            }
        }
        $this->listing->forceFill(['expires_at' => now()->addMinutes(10)])->save();
        $this->expectException(ValidationException::class);
        $this->card(['starts_at' => now()->addHour()]);
    }

    public function test_effective_end_is_earliest_banner_campaign_or_listing_deadline(): void
    {
        $campaign = Campaign::create(['title' => 'Timed campaign', 'is_active' => true, 'ends_at' => now()->addHour()]);
        $card = $this->card(['campaign_id' => $campaign->id]);
        $this->assertSame($campaign->ends_at->timestamp, $card->effectiveDeadline()->timestamp);
        $this->listing->forceFill(['expires_at' => now()->addMinutes(15)])->save();
        $this->assertSame($this->listing->expires_at->timestamp, $card->fresh()->effectiveDeadline()->timestamp);
        $card->update(['ends_at' => now()->addMinutes(5)]);
        $this->assertSame($card->ends_at->timestamp, $card->fresh()->effectiveDeadline()->timestamp);
    }

    public function test_exact_expiry_stops_rendering_destination_and_events_without_resetting(): void
    {
        $card = $this->card(['ends_at' => now()->addMinutes(2)]);
        $token = $this->token($card);
        $original = $card->ends_at->timestamp;
        $this->travelTo($card->ends_at);
        $this->assertFalse($card->fresh()->deadlineEligible());
        $this->assertNull($card->fresh()->destination());
        $this->get('/')->assertOk()->assertDontSee('data-ad-deadline=', false);
        foreach (['impression', 'click'] as $kind) {
            $this->withCredentials()->postJson(route('advertising.events'), ['token' => $token, 'kind' => $kind, 'device' => 'mobile'])->assertNoContent();
        }
        $this->assertSame(0, AdvertisingEvent::where('banner_id', $card->id)->count());
        $card->update(['is_active' => false]);
        $this->assertSame($original, $card->fresh()->ends_at->timestamp);
        $this->travelBack();
    }

    public function test_unavailable_listing_or_owner_hides_card_and_warns(): void
    {
        $card = $this->card();
        $this->listing->forceFill(['status' => 'sold'])->save();
        $this->assertNull($card->fresh()->advertisedDeadlineListing());
        $this->get('/')->assertOk()->assertDontSee('data-ad-deadline=', false);
        $this->assertContains('deadline_unavailable', array_column(Banner::healthIssues(), 'code'));
        $card->update(['is_active' => false]);
        $this->listing->forceFill(['status' => 'active'])->save();
        $this->seller->update(['status' => 'suspended']);
        $this->assertFalse($card->fresh()->deadlineEligible());
    }

    public function test_missing_listing_cannot_be_activated(): void
    {
        $this->expectException(ValidationException::class);
        $this->card(['offer_listing_id' => 999999999]);
    }

    public function test_staff_extension_requires_review_and_approval_preserves_fixed_window(): void
    {
        $card = $this->card();
        $staff = User::factory()->create(['status' => 'active']);
        $staff->syncRoles(['advertising-manager']);
        $this->actingAs($staff);
        $end = now()->addHours(3)->startOfMinute();
        $card->update(['ends_at' => $end]);
        $this->assertSame('pending', $card->fresh()->review_status);
        $this->assertFalse($card->fresh()->is_active);
        $staff->syncRoles(['moderator']);
        Banner::moderationQueue()->firstWhere('id', $card->id)->moderate(true);
        $this->assertSame($this->listing->id, (int) $card->fresh()->offer_listing_id);
        $this->assertSame($end->timestamp, $card->fresh()->ends_at->timestamp);
        $this->assertTrue($card->fresh()->deadlineEligible());
    }

    public function test_changed_deadline_invalidates_unused_old_token_even_after_admin_extension(): void
    {
        $card = $this->card();
        $token = $this->token($card);
        $card->update(['ends_at' => now()->addHours(4)]);
        $this->travel(2)->seconds();
        foreach (['impression', 'click'] as $kind) {
            $this->withCredentials()->postJson(route('advertising.events'), ['token' => $token, 'kind' => $kind, 'device' => 'mobile'])->assertNoContent();
        }
        $this->assertSame(0, AdvertisingEvent::where('banner_id', $card->id)->count());
        $this->travelBack();
    }

    public function test_real_click_deduplicates_and_actual_new_conversation_can_be_attributed(): void
    {
        $card = $this->card();
        $this->actingAs(User::factory()->create(['status' => 'active']));
        $token = $this->token($card);
        $response = $this->withCredentials()->postJson(route('advertising.events'), ['token' => $token, 'kind' => 'click', 'device' => 'mobile'])->assertNoContent();
        $this->withCookie('advertising_attribution', $response->getCookie('advertising_attribution')->getValue());
        $this->withCredentials()->postJson(route('advertising.events'), ['token' => $token, 'kind' => 'click', 'device' => 'mobile'])->assertNoContent();
        $this->assertSame(1, AdvertisingEvent::where('banner_id', $card->id)->where('kind', 'click')->count());
        $this->withCredentials()->postJson(route('conversations.start', $this->listing), ['message' => 'Still available?'])->assertSuccessful();
        $this->assertSame(1, AdvertisingEvent::where('banner_id', $card->id)->where('kind', 'conversion')->count());
    }

    public function test_campaign_device_schedule_and_placement_rules_are_reused(): void
    {
        $campaign = Campaign::create(['title' => 'Timed targeting', 'is_active' => true, 'page_paths' => ['/allowed']]);
        $card = $this->card(['campaign_id' => $campaign->id, 'device' => 'mobile']);
        $this->assertFalse($card->eligibleForCampaign(['path' => 'other']));
        $result = (new BannerRenderer)->at('home-top', context: ['path' => 'allowed']);
        $this->assertSame($card->id, $result['mobile']->id);
        $this->assertArrayNotHasKey('desktop', $result);
        $this->assertFalse($card->eligibleForPlacement('category-top'));
        $card->update(['starts_at' => now()->addHour()]);
        $this->assertSame([], (new BannerRenderer)->at('home-top', context: ['path' => 'allowed']));
    }

    public function test_filament_save_uses_tehran_deadline_and_preview_has_no_live_timer_or_tracking(): void
    {
        $card = $this->card(['ends_at' => now()->addHours(2)->startOfMinute()]);
        $timestamp = $card->ends_at->timestamp;
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $this->actingAs(User::where('email', 'a@a.com')->firstOrFail());
        Livewire::test(EditBanner::class, ['record' => $card->id])->call('save')->assertHasNoFormErrors();
        $this->assertSame($timestamp, $card->fresh()->ends_at->timestamp);
        $this->get(route('advertising.preview-render', ['banner' => $card->id]))->assertOk()->assertSee('data-ad-deadline=', false)->assertDontSee('data-behavior="advertising-deadline"', false)->assertDontSee('data-event-token=', false)->assertDontSee(route('listings.show', $this->listing));
        $this->actingAs($this->seller)->get('/admin/banners/'.$card->id.'/edit')->assertForbidden();
    }
}
