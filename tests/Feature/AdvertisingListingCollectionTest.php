<?php

declare(strict_types=1);

namespace Tests\Feature;

use Filament\Facades\Filament;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
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

final class AdvertisingListingCollectionTest extends TestCase
{
    private User $seller;

    private array $ids;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
        $image = UploadedFile::fake()->image('collection.jpg');
        Storage::disk('public')->put('banners/collection.jpg', file_get_contents($image->getRealPath()));
        $this->seller = User::factory()->create(['status' => 'active']);
        $this->ids = [];
        foreach (range(1, 3) as $number) {
            $listing = Listing::unguarded(fn () => Listing::create(['title' => 'Collection phone '.$number, 'description' => 'Public fixture', 'price' => 1000 * $number, 'currency' => 'IRR', 'user_id' => $this->seller->id, 'category_id' => Category::firstOrFail()->id, 'slug' => (string) Str::uuid(), 'status' => 'active']));
            $this->ids[] = $listing->id;
        }
    }

    private function card(array $data = []): Banner
    {
        $banner = Banner::create(array_replace(['title' => 'Sponsored collection', 'alt' => 'Collection cover', 'image' => 'banners/collection.jpg', 'creative_format' => 'collection', 'collection_listing_ids' => $this->ids, 'device' => 'both', 'is_active' => true, 'review_status' => 'approved'], $data));
        BannerPlacement::where('key', 'home-top')->firstOrFail()->assignments()->create(['banner_id' => $banner->id, 'priority' => 1]);

        return $banner;
    }

    private function token(Banner $card): string
    {
        $this->get('/')->assertOk();
        $token = AdvertisingToken::issue($card, 'home-top', 'both', []);
        $this->withCookie(config('session.cookie'), request()->session()->getId());

        return $token;
    }

    private function click(string $token, ?int $id): TestResponse
    {
        return $this->withCredentials()->postJson(route('advertising.events'), ['token' => $token, 'kind' => 'click', 'device' => 'mobile', 'listing' => $id])->assertNoContent();
    }

    public function test_collection_renders_ordered_public_listing_links_and_current_prices(): void
    {
        $ids = array_reverse($this->ids);
        $card = $this->card(['collection_listing_ids' => $ids, 'url' => 'https://unrelated.example']);
        $this->assertNull($card->destination());
        $this->assertSame($ids, array_keys($card->advertisedListings()));
        $response = $this->get('/')->assertOk()->assertSee('data-ad-collection="'.$card->id.'"', false)->assertDontSee('https://unrelated.example');
        foreach ($ids as $id) {
            $response->assertSee('data-ad-listing="'.$id.'"', false)->assertSee(route('listings.show', $id));
        }
        Listing::findOrFail($ids[0])->update(['title' => '<script>collection()</script>', 'price' => 7000]);
        $this->get('/')->assertOk()->assertSee('&lt;script&gt;collection()&lt;/script&gt;', false)->assertDontSee('<script>collection()</script>', false);
        $this->assertSame(Listing::findOrFail($ids[0])->panelPriceLabel(), $card->fresh()->advertisedListings()[$ids[0]]['price']);
    }

    public function test_unavailable_items_are_omitted_and_fewer_than_two_suppresses_the_unit(): void
    {
        $card = $this->card();
        Listing::findOrFail($this->ids[0])->forceFill(['status' => 'paused'])->save();
        $this->assertCount(2, $card->fresh()->advertisedListings());
        $this->get('/')->assertOk()->assertSee('data-ad-collection="'.$card->id.'"', false)->assertDontSee('data-ad-listing="'.$this->ids[0].'"', false);
        $this->assertContains('collection_unavailable', array_column(Banner::healthIssues(), 'code'));
        Listing::findOrFail($this->ids[1])->update(['expires_at' => now()->subSecond()]);
        $this->assertFalse($card->fresh()->creativeEligible());
        $this->assertSame([], (new BannerRenderer)->at('home-top'));
        $card->update(['is_active' => false]);
        $this->assertFalse($card->fresh()->is_active);
    }

    public function test_inactive_or_deleted_owners_and_deleted_listings_are_not_advertised(): void
    {
        $card = $this->card();
        Listing::findOrFail($this->ids[0])->delete();
        $this->assertCount(2, $card->fresh()->advertisedListings());
        $this->seller->update(['status' => 'suspended']);
        $this->assertSame([], $card->fresh()->advertisedListings());
        $this->seller->update(['status' => 'active']);
        $this->seller->delete();
        $this->assertSame([], $card->fresh()->advertisedListings());
    }

    public function test_selection_requires_two_to_six_distinct_current_public_listings(): void
    {
        foreach ([[], [$this->ids[0]], [$this->ids[0], $this->ids[0]], [$this->ids[0], 999999999], array_fill(0, 7, $this->ids[0])] as $ids) {
            try {
                $this->card(['collection_listing_ids' => $ids]);
                $this->fail('Invalid collection was accepted');
            } catch (ValidationException $exception) {
                $this->assertArrayHasKey('collection_listing_ids', $exception->errors());
            }
        }
        $this->assertSame([], Listing::advertisingCollectionItems([]));
    }

    public function test_clicks_are_bound_to_served_selection_and_deduplicated_per_exposure(): void
    {
        $card = $this->card();
        $token = $this->token($card);
        $this->click($token, null);
        $this->click($token, 999999999);
        $this->assertSame(0, AdvertisingEvent::where('banner_id', $card->id)->count());
        $this->click($token, $this->ids[1])->assertCookie('advertising_attribution');
        $this->click($token, $this->ids[2]);
        $events = AdvertisingEvent::where('banner_id', $card->id)->get();
        $this->assertCount(1, $events);
        $this->assertSame($this->ids[1], (int) $events->first()->target_listing_id);
    }

    public function test_old_tokens_cannot_click_newly_selected_or_now_unavailable_items(): void
    {
        $card = $this->card(['collection_listing_ids' => array_slice($this->ids, 0, 2)]);
        $token = $this->token($card);
        $card->update(['collection_listing_ids' => $this->ids]);
        $this->click($token, $this->ids[2]);
        Listing::findOrFail($this->ids[0])->forceFill(['status' => 'sold'])->save();
        $this->click($token, $this->ids[0]);
        $this->assertSame(0, AdvertisingEvent::where('banner_id', $card->id)->count());
    }

    public function test_collection_click_can_attribute_only_its_actual_new_conversation(): void
    {
        $card = $this->card();
        $this->actingAs(User::factory()->create(['status' => 'active']));
        $response = $this->click($this->token($card), $this->ids[1]);
        $this->withCookie('advertising_attribution', $response->getCookie('advertising_attribution')->getValue());
        $this->withCredentials()->postJson(route('conversations.start', $this->ids[0]), ['message' => 'Still available?'])->assertSuccessful();
        $this->assertSame(0, AdvertisingEvent::where('banner_id', $card->id)->where('kind', 'conversion')->count());
        $this->withCredentials()->postJson(route('conversations.start', $this->ids[1]), ['message' => 'Still available?'])->assertSuccessful();
        $this->assertSame(1, AdvertisingEvent::where('banner_id', $card->id)->where('kind', 'conversion')->count());
    }

    public function test_staff_selection_changes_reenter_moderation_and_approval_preserves_selection(): void
    {
        $card = $this->card();
        $staff = User::factory()->create(['status' => 'active']);
        $staff->syncRoles(['advertising-manager']);
        $this->actingAs($staff);
        $card->update(['collection_listing_ids' => array_reverse($this->ids)]);
        $this->assertSame('pending', $card->fresh()->review_status);
        $this->assertFalse($card->fresh()->is_active);
        $staff->syncRoles(['moderator']);
        Banner::moderationQueue()->firstWhere('id', $card->id)->moderate(true);
        $this->assertSame(array_reverse($this->ids), $card->fresh()->collection_listing_ids);
        $this->assertTrue($card->fresh()->creativeEligible());
    }

    public function test_existing_campaign_targeting_device_and_preview_rules_apply_to_the_whole_collection(): void
    {
        $campaign = Campaign::create(['title' => 'Collection campaign', 'is_active' => true, 'page_paths' => ['/allowed']]);
        $card = $this->card(['campaign_id' => $campaign->id, 'device' => 'mobile']);
        $this->assertFalse($card->eligibleForCampaign(['path' => 'other']));
        $result = (new BannerRenderer)->at('home-top', context: ['path' => 'allowed']);
        $this->assertSame($card->id, $result['mobile']->id);
        $this->assertArrayNotHasKey('desktop', $result);
        $card->update(['starts_at' => now()->addDay()]);
        $this->assertSame([], (new BannerRenderer)->at('home-top', context: ['path' => 'allowed']));
        $this->actingAs(User::where('email', 'a@a.com')->firstOrFail());
        $this->get(route('advertising.preview-render', ['banner' => $card->id, 'device' => 'mobile', 'placement' => 'home-top']))->assertOk()->assertSee('data-ad-collection="'.$card->id.'"', false)->assertDontSee('data-ad-listing=', false)->assertDontSee('data-event-token=', false);
    }

    public function test_filament_save_preserves_format_and_plain_users_cannot_manage_collection(): void
    {
        $card = $this->card();
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $this->actingAs(User::where('email', 'a@a.com')->firstOrFail());
        Livewire::test(EditBanner::class, ['record' => $card->id])->fillForm(['creative_format' => 'collection', 'collection_listing_ids' => $this->ids])->call('save')->assertHasNoFormErrors();
        $this->assertSame('collection', $card->fresh()->creative_format);
        $this->assertArrayHasKey($this->ids[0], Listing::advertisingCollectionOptions('Collection phone'));
        $this->actingAs($this->seller)->get('/admin/banners/'.$card->id.'/edit')->assertForbidden();
    }
}
