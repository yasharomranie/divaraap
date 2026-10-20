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
use Modules\Conversation\App\Models\Conversation;
use Modules\Conversation\App\Models\ConversationBlock;
use Modules\Conversation\App\Models\ConversationMessage;
use Modules\Listing\Models\Listing;
use Modules\User\App\Models\User;
use Tests\TestCase;

final class AdvertisingServiceCardTest extends TestCase
{
    private User $seller;

    private User $buyer;

    private Listing $listing;

    private Category $category;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
        $image = UploadedFile::fake()->image('service.jpg');
        Storage::disk('public')->put('banners/service.jpg', file_get_contents($image->getRealPath()));
        $this->seller = User::factory()->create(['name' => 'PRIVATE PROVIDER', 'status' => 'active']);
        $this->buyer = User::factory()->create(['status' => 'active']);
        $this->category = Category::create(['name' => 'Service fixture', 'slug' => (string) Str::uuid(), 'parent_id' => Category::where('slug', 'services')->firstOrFail()->id, 'is_active' => true]);
        $this->listing = $this->listing();
    }

    private function listing(array $data = []): Listing
    {
        return Listing::unguarded(fn () => Listing::create(array_replace(['title' => 'Service repair fixture', 'description' => 'Public service details', 'price' => 100000, 'currency' => 'IRR', 'user_id' => $this->seller->id, 'category_id' => $this->category->id, 'slug' => (string) Str::uuid(), 'status' => 'active'], $data)));
    }

    private function card(array $data = []): Banner
    {
        $card = Banner::create(array_replace(['title' => 'Service campaign', 'alt' => 'Service photo', 'image' => 'banners/service.jpg', 'creative_format' => 'service', 'service_listing_id' => $this->listing->id, 'device' => 'both', 'is_active' => true, 'review_status' => 'approved'], $data));
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

    public function test_card_uses_live_public_service_and_internal_request_destination(): void
    {
        $card = $this->card(['url' => 'https://unrelated.example']);
        $this->assertSame(route('listings.show', ['listing' => $this->listing->id, 'request-service' => 1]).'#service-request', $card->destination());
        $this->get('/')->assertOk()->assertSee('data-ad-service="'.$this->listing->id.'"', false)->assertSee($this->listing->title)->assertDontSee('PRIVATE PROVIDER')->assertDontSee($this->seller->email)->assertDontSee('https://unrelated.example');
        $this->listing->update(['title' => '<script>service()</script>', 'price' => 200000]);
        $this->get('/')->assertOk()->assertSee('&lt;script&gt;service()&lt;/script&gt;', false)->assertDontSee('<script>service()</script>', false);
        $this->assertSame($this->listing->panelPriceLabel(), $card->fresh()->advertisedService()['price']);
    }

    public function test_paused_expired_deleted_or_inactive_provider_suppresses_delivery(): void
    {
        $card = $this->card();
        $this->listing->forceFill(['status' => 'paused'])->save();
        $this->assertFalse($card->fresh()->creativeEligible());
        $this->assertNull($card->fresh()->destination());
        $this->assertSame([], (new BannerRenderer)->at('home-top'));
        $this->assertContains('service_unavailable', array_column(Banner::healthIssues(), 'code'));
        $card->update(['is_active' => false]);
        $this->listing->forceFill(['status' => 'active', 'expires_at' => now()->subSecond()])->save();
        $this->assertNull($card->fresh()->advertisedService());
        $this->listing->forceFill(['expires_at' => null])->save();
        $this->seller->update(['status' => 'suspended']);
        $this->assertNull($card->fresh()->advertisedService());
        $this->seller->update(['status' => 'active']);
        $this->listing->delete();
        $this->assertNull($card->fresh()->advertisedService());
    }

    public function test_only_active_service_category_ancestry_is_selectable(): void
    {
        $this->assertArrayHasKey($this->listing->id, Listing::advertisingServiceOptions('Service repair'));
        $this->category->update(['is_active' => false]);
        $this->assertSame([], Listing::advertisingServiceItems([$this->listing->id]));
        $this->category->update(['is_active' => true]);
        $this->listing->update(['category_id' => Category::where('slug', 'electronics')->firstOrFail()->id]);
        $this->assertSame([], Listing::advertisingServiceItems([$this->listing->id]));
        $this->expectException(ValidationException::class);
        $this->card();
    }

    public function test_invalid_or_missing_service_cannot_activate_but_existing_card_can_pause(): void
    {
        foreach ([null, 999999999] as $id) {
            try {
                $this->card(['service_listing_id' => $id]);
                $this->fail('Invalid service was accepted');
            } catch (ValidationException $exception) {
                $this->assertArrayHasKey('service_listing_id', $exception->errors());
            }
        }
        $card = $this->card();
        $this->listing->forceFill(['status' => 'sold'])->save();
        $card->update(['is_active' => false]);
        $this->assertFalse($card->fresh()->is_active);
        $this->assertSame([], Listing::advertisingServiceItems([]));
    }

    public function test_form_is_explicit_and_guest_owner_and_inactive_accounts_cannot_send(): void
    {
        $path = route('listings.show', ['listing' => $this->listing->id, 'request-service' => 1]);
        $this->get($path)->assertOk()->assertSee(__('listing::service.login'))->assertDontSee('id="service-request-message"', false);
        $this->actingAs($this->buyer)->get($path)->assertOk()->assertSee('id="service-request-message"', false)->assertSee(__('listing::service.privacy'));
        $this->get(route('listings.show', $this->listing))->assertOk()->assertDontSee('id="service-request-message"', false);
        $this->actingAs($this->seller)->postJson(route('conversations.start', $this->listing), ['service_request' => 1, 'message' => 'Need service'])->assertUnprocessable();
        $this->buyer->update(['status' => 'suspended']);
        $this->actingAs($this->buyer)->postJson(route('conversations.start', $this->listing), ['service_request' => 1, 'message' => 'Need service'])->assertForbidden();
    }

    public function test_service_request_requires_human_text_and_reuses_conversation_and_message(): void
    {
        $this->actingAs($this->buyer);
        foreach (['', '   ', str_repeat('a', 2001)] as $message) {
            $this->postJson(route('conversations.start', $this->listing), ['service_request' => 1, 'message' => $message])->assertUnprocessable();
        }
        $this->assertSame(0, Conversation::where('listing_id', $this->listing->id)->count());
        $this->postJson(route('conversations.start', $this->listing), ['service_request' => 1, 'message' => 'Please repair my chair'])->assertSuccessful();
        $conversation = Conversation::where('listing_id', $this->listing->id)->firstOrFail();
        $this->assertSame(1, ConversationMessage::where('conversation_id', $conversation->id)->count());
        $this->postJson(route('conversations.start', $this->listing), ['service_request' => 1, 'message' => 'Tomorrow afternoon works'])->assertSuccessful();
        $this->assertSame(1, Conversation::where('listing_id', $this->listing->id)->count());
        $this->assertSame(2, ConversationMessage::where('conversation_id', $conversation->id)->count());
    }

    public function test_existing_bidirectional_blocks_prevent_service_requests_without_a_new_thread(): void
    {
        ConversationBlock::setFor($this->seller, $this->buyer->id, true);
        $this->actingAs($this->buyer)->postJson(route('conversations.start', $this->listing), ['service_request' => 1, 'message' => 'Need repair'])->assertUnprocessable();
        $this->assertSame(0, Conversation::where('listing_id', $this->listing->id)->count());
    }

    public function test_unavailable_or_nonservice_requests_cannot_create_conversations(): void
    {
        $this->actingAs($this->buyer);
        $this->listing->forceFill(['status' => 'sold'])->save();
        $this->postJson(route('conversations.start', $this->listing), ['service_request' => 1, 'message' => 'Need repair'])->assertUnprocessable();
        $this->listing->forceFill(['status' => 'active', 'category_id' => Category::where('slug', 'electronics')->firstOrFail()->id])->save();
        $this->postJson(route('conversations.start', $this->listing), ['service_request' => 1, 'message' => 'Need repair'])->assertUnprocessable();
        $this->assertSame(0, Conversation::where('listing_id', $this->listing->id)->count());
    }

    public function test_click_deduplication_and_real_conversation_attribution_are_preserved(): void
    {
        $card = $this->card();
        $this->actingAs($this->buyer);
        $token = $this->token($card);
        $response = $this->withCredentials()->postJson(route('advertising.events'), ['token' => $token, 'kind' => 'click', 'device' => 'mobile'])->assertNoContent();
        $this->withCookie('advertising_attribution', $response->getCookie('advertising_attribution')->getValue());
        $this->withCredentials()->postJson(route('advertising.events'), ['token' => $token, 'kind' => 'click', 'device' => 'mobile'])->assertNoContent();
        $this->assertSame(1, AdvertisingEvent::where('banner_id', $card->id)->where('kind', 'click')->count());
        $this->withCredentials()->postJson(route('conversations.start', $this->listing), ['service_request' => 1, 'message' => 'Need a repair'])->assertSuccessful();
        $conversion = AdvertisingEvent::where('banner_id', $card->id)->where('kind', 'conversion')->firstOrFail();
        $this->assertSame($this->listing->id, (int) $conversion->target_listing_id);
    }

    public function test_old_service_token_cannot_attribute_a_changed_target_or_format(): void
    {
        $card = $this->card();
        $token = $this->token($card);
        $other = $this->listing(['title' => 'Other service']);
        $card->update(['service_listing_id' => $other->id]);
        $this->withCredentials()->postJson(route('advertising.events'), ['token' => $token, 'kind' => 'click', 'device' => 'mobile'])->assertNoContent();
        $card->update(['creative_format' => 'image', 'url' => route('listings.show', $other)]);
        $this->withCredentials()->postJson(route('advertising.events'), ['token' => $token, 'kind' => 'click', 'device' => 'mobile'])->assertNoContent();
        $this->assertSame(0, AdvertisingEvent::where('banner_id', $card->id)->count());
    }

    public function test_staff_binding_changes_reenter_moderation_and_approval_keeps_target(): void
    {
        $card = $this->card();
        $other = $this->listing();
        $staff = User::factory()->create(['status' => 'active']);
        $staff->syncRoles(['advertising-manager']);
        $this->actingAs($staff);
        $card->update(['service_listing_id' => $other->id]);
        $this->assertSame('pending', $card->fresh()->review_status);
        $this->assertFalse($card->fresh()->is_active);
        $staff->syncRoles(['moderator']);
        Banner::moderationQueue()->firstWhere('id', $card->id)->moderate(true);
        $this->assertSame($other->id, (int) $card->fresh()->service_listing_id);
        $this->assertTrue($card->fresh()->creativeEligible());
    }

    public function test_campaign_device_preview_and_filament_permissions_are_reused(): void
    {
        $campaign = Campaign::create(['title' => 'Service campaign', 'is_active' => true, 'page_paths' => ['/allowed']]);
        $card = $this->card(['campaign_id' => $campaign->id, 'device' => 'mobile']);
        $this->assertFalse($card->eligibleForCampaign(['path' => 'other']));
        $result = (new BannerRenderer)->at('home-top', context: ['path' => 'allowed']);
        $this->assertSame($card->id, $result['mobile']->id);
        $this->assertArrayNotHasKey('desktop', $result);
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $this->actingAs(User::where('email', 'a@a.com')->firstOrFail());
        Livewire::test(EditBanner::class, ['record' => $card->id])->call('save')->assertHasNoFormErrors();
        $this->assertSame('service', $card->fresh()->creative_format);
        $this->get(route('advertising.preview-render', ['banner' => $card->id, 'device' => 'mobile']))->assertOk()->assertSee('data-ad-service=', false)->assertDontSee('data-event-token=', false)->assertDontSee('#service-request');
        $this->actingAs($this->buyer)->get('/admin/banners/'.$card->id.'/edit')->assertForbidden();
    }
}
