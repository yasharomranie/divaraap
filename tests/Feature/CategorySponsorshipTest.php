<?php

declare(strict_types=1);

namespace Tests\Feature;

use Filament\Facades\Filament;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
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
use Modules\User\App\Models\Profile;
use Modules\User\App\Models\User;
use Tests\TestCase;

final class CategorySponsorshipTest extends TestCase
{
    private User $seller;

    private Category $category;

    private Category $child;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
        $image = UploadedFile::fake()->image('sponsor.jpg');
        Storage::disk('public')->put('banners/sponsor.jpg', file_get_contents($image->getRealPath()));
        $this->seller = User::factory()->create(['name' => 'PRIVATE SPONSOR OWNER', 'status' => 'active']);
        Profile::saveShopFor($this->seller, ['shop_enabled' => true, 'shop_name' => 'Public sponsor shop', 'shop_description' => 'Public introduction', 'shop_hours' => '9–18'], Profile::shopVersion($this->seller));
        $this->category = Category::create(['name' => 'Sponsor category fixture', 'slug' => (string) Str::uuid(), 'is_active' => true]);
        $this->child = Category::create(['name' => 'Sponsor child fixture', 'slug' => (string) Str::uuid(), 'parent_id' => $this->category->id, 'is_active' => true]);
    }

    private function card(array $data = [], int $priority = 1): Banner
    {
        $card = Banner::create(array_replace(['title' => 'Category support headline', 'alt' => 'Sponsor logo', 'image' => 'banners/sponsor.jpg', 'creative_format' => 'sponsor', 'shop_user_id' => $this->seller->id, 'sponsored_category_id' => $this->category->id, 'device' => 'both', 'is_active' => true, 'review_status' => 'approved'], $data));
        BannerPlacement::where('key', 'category-top')->firstOrFail()->assignments()->create(['banner_id' => $card->id, 'priority' => $priority]);

        return $card;
    }

    private function token(Banner $card, ?int $category = null, string $placement = 'category-top'): string
    {
        $this->get('/listings?category='.$this->category->id)->assertOk();
        $token = AdvertisingToken::issue($card, $placement, 'both', ['category_id' => $category ?? $this->category->id]);
        $this->withCookie(config('session.cookie'), request()->session()->getId());

        return $token;
    }

    public function test_sponsor_renders_above_empty_exact_category_and_links_only_existing_public_shop(): void
    {
        $card = $this->card(['url' => 'https://unrelated.example']);
        $this->assertSame(route('sellers.show', $this->seller->id), $card->destination());
        $this->get('/listings?category='.$this->category->id)->assertOk()->assertSee('data-ad-sponsor="'.$this->category->id.'"', false)->assertSee('Public sponsor shop')->assertSee(__('advertising::sponsor.disclosure'))->assertSee(route('sellers.show', $this->seller->id))->assertDontSee('PRIVATE SPONSOR OWNER')->assertDontSee($this->seller->email)->assertDontSee('https://unrelated.example');
        $this->get('/listings?category='.$this->category->id.'&search=unmatched-query')->assertOk()->assertSee('data-ad-sponsor=', false);
    }

    public function test_home_all_categories_descendants_other_categories_and_map_are_not_included(): void
    {
        $this->card();
        foreach (['/', '/listings', '/listings?category='.$this->child->id, '/listings?category='.Category::where('slug', 'electronics')->firstOrFail()->id, '/listings/map?category='.$this->category->id] as $path) {
            $this->get($path)->assertOk()->assertDontSee('data-ad-sponsor=', false);
        }
    }

    public function test_inactive_or_deleted_category_ancestry_suppresses_and_warns_but_allows_pausing(): void
    {
        $card = $this->card(['sponsored_category_id' => $this->child->id]);
        $this->category->update(['is_active' => false]);
        $this->assertFalse($card->fresh()->creativeEligible());
        $this->assertNull($card->fresh()->destination());
        $this->assertContains('sponsor_category_unavailable', array_column(Banner::healthIssues(), 'code'));
        $card->update(['is_active' => false]);
        $this->assertFalse($card->fresh()->is_active);
        $this->category->update(['is_active' => true]);
        $this->child->delete();
        $this->assertNull($card->fresh()->sponsoredCategoryName());
    }

    public function test_changed_shop_snapshot_or_inactive_owner_suppresses_the_sponsor(): void
    {
        $card = $this->card();
        Profile::detailsForUser($this->seller)->forceFill(['shop_name' => 'Changed sponsor name'])->save();
        $this->assertFalse($card->fresh()->creativeEligible());
        $this->get('/listings?category='.$this->category->id)->assertOk()->assertDontSee('data-ad-sponsor=', false);
        $card->save();
        $this->assertTrue($card->fresh()->creativeEligible());
        $this->seller->update(['status' => 'suspended']);
        $this->assertFalse($card->fresh()->creativeEligible());
    }

    public function test_invalid_category_or_shop_cannot_be_activated(): void
    {
        foreach ([['sponsored_category_id' => null], ['sponsored_category_id' => 999999999], ['shop_user_id' => null]] as $data) {
            try {
                $this->card($data);
                $this->fail('Invalid sponsorship was accepted');
            } catch (ValidationException $exception) {
                $this->assertNotEmpty($exception->errors());
            }
        }
    }

    public function test_incompatible_assignment_is_rejected_and_legacy_bad_assignment_cannot_render(): void
    {
        $card = $this->card();
        $placement = BannerPlacement::where('key', 'home-top')->firstOrFail();
        try {
            $placement->assignments()->create(['banner_id' => $card->id, 'priority' => 1]);
            $this->fail('Wrong placement accepted');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('placement_id', $exception->errors());
        }
        DB::table('advertising_assignments')->insert(['banner_id' => $card->id, 'placement_id' => $placement->id, 'priority' => 1, 'created_at' => now(), 'updated_at' => now()]);
        $this->assertSame([], (new BannerRenderer)->at('home-top', context: ['category_id' => $this->category->id]));
        $this->assertFalse($card->eligibleForPlacement('category-feed'));
        $image = Banner::create(['title' => 'Plain', 'image' => 'banners/sponsor.jpg', 'alt' => 'Plain', 'device' => 'both', 'is_active' => true]);
        $this->assertFalse($image->eligibleForPlacement('category-top'));
    }

    public function test_multiple_sponsors_use_one_header_slot_with_existing_priority(): void
    {
        $later = $this->card(['title' => 'Later sponsor'], priority: 5);
        $first = $this->card(['title' => 'First sponsor'], priority: 1);
        $result = (new BannerRenderer)->at('category-top', context: ['category_id' => $this->category->id]);
        $this->assertCount(1, $result);
        $this->assertSame($first->id, $result['both']->id);
        $this->get('/listings?category='.$this->category->id)->assertOk()->assertSee('data-banner="'.$first->id.'"', false)->assertDontSee('data-banner="'.$later->id.'"', false);
    }

    public function test_campaign_and_device_rules_intersect_with_exact_sponsored_category(): void
    {
        $campaign = Campaign::create(['title' => 'Sponsor campaign', 'is_active' => true, 'category_ids' => [$this->category->id], 'page_paths' => ['/listings']]);
        $card = $this->card(['campaign_id' => $campaign->id, 'device' => 'mobile']);
        $this->assertFalse($card->eligibleForCampaign(['category_id' => $this->child->id, 'path' => 'listings']));
        $this->assertFalse($card->eligibleForCampaign(['category_id' => $this->category->id, 'path' => 'other']));
        $result = (new BannerRenderer)->at('category-top', context: ['category_id' => $this->category->id, 'path' => 'listings']);
        $this->assertSame($card->id, $result['mobile']->id);
        $this->assertArrayNotHasKey('desktop', $result);
        $card->update(['starts_at' => now()->addDay()]);
        $this->assertSame([], (new BannerRenderer)->at('category-top', context: ['category_id' => $this->category->id, 'path' => 'listings']));
    }

    public function test_sponsor_events_require_category_context_placement_and_current_binding(): void
    {
        $card = $this->card();
        foreach ([$this->token($card, $this->child->id), $this->token($card, placement: 'home-top')] as $token) {
            $this->withCredentials()->postJson(route('advertising.events'), ['token' => $token, 'kind' => 'click', 'device' => 'mobile'])->assertNoContent();
        }
        $this->assertSame(0, AdvertisingEvent::where('banner_id', $card->id)->count());
        $token = $this->token($card);
        $this->travel(2)->seconds();
        $this->withCredentials()->postJson(route('advertising.events'), ['token' => $token, 'kind' => 'impression', 'device' => 'mobile'])->assertNoContent();
        foreach (range(1, 2) as $attempt) {
            $this->withCredentials()->postJson(route('advertising.events'), ['token' => $token, 'kind' => 'click', 'device' => 'mobile'])->assertNoContent()->assertCookieMissing('advertising_attribution');
        }
        $this->assertSame(2, AdvertisingEvent::where('banner_id', $card->id)->count());
        $card->update(['sponsored_category_id' => $this->child->id]);
        $this->withCredentials()->postJson(route('advertising.events'), ['token' => $token, 'kind' => 'click', 'device' => 'mobile'])->assertNoContent();
        $this->assertSame(2, AdvertisingEvent::where('banner_id', $card->id)->count());
        $this->travelBack();
    }

    public function test_old_sponsorship_tokens_cannot_count_after_shop_refresh_or_format_change(): void
    {
        $card = $this->card();
        $token = $this->token($card);
        Profile::detailsForUser($this->seller)->forceFill(['shop_name' => 'Updated public sponsor'])->save();
        $card->save();
        $this->assertTrue($card->fresh()->creativeEligible());
        $this->travel(2)->seconds();
        foreach (['click', 'impression'] as $kind) {
            $this->withCredentials()->postJson(route('advertising.events'), ['token' => $token, 'kind' => $kind, 'device' => 'mobile'])->assertNoContent();
        }
        $card->update(['creative_format' => 'shop']);
        $this->withCredentials()->postJson(route('advertising.events'), ['token' => $token, 'kind' => 'click', 'device' => 'mobile'])->assertNoContent();
        $this->assertSame(0, AdvertisingEvent::where('banner_id', $card->id)->count());
        $this->travelBack();
    }

    public function test_staff_category_change_reenters_moderation_and_keeps_binding_on_approval(): void
    {
        $card = $this->card();
        $staff = User::factory()->create(['status' => 'active']);
        $staff->syncRoles(['advertising-manager']);
        $this->actingAs($staff);
        $card->update(['sponsored_category_id' => $this->child->id]);
        $this->assertSame('pending', $card->fresh()->review_status);
        $this->assertFalse($card->fresh()->is_active);
        $staff->syncRoles(['moderator']);
        Banner::moderationQueue()->firstWhere('id', $card->id)->moderate(true);
        $this->assertSame($this->child->id, (int) $card->fresh()->sponsored_category_id);
        $this->assertSame($this->seller->id, (int) $card->fresh()->shop_user_id);
        $this->assertTrue($card->fresh()->creativeEligible());
    }

    public function test_filament_can_save_sponsor_and_correct_preview_is_untracked_and_escaped(): void
    {
        Profile::detailsForUser($this->seller)->forceFill(['shop_name' => '<script>sponsor()</script>'])->save();
        $card = $this->card();
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $this->actingAs(User::where('email', 'a@a.com')->firstOrFail());
        Livewire::test(EditBanner::class, ['record' => $card->id])->call('save')->assertHasNoFormErrors();
        $this->get(route('advertising.preview-render', ['banner' => $card->id, 'placement' => 'category-top']))->assertOk()->assertSee('&lt;script&gt;sponsor()&lt;/script&gt;', false)->assertDontSee('<script>sponsor()</script>', false)->assertDontSee('data-event-token=', false)->assertDontSee(route('sellers.show', $this->seller->id));
        $this->get(route('advertising.preview-render', ['banner' => $card->id, 'placement' => 'home-top']))->assertOk()->assertDontSee('data-ad-sponsor=', false);
        $this->actingAs($this->seller)->get('/admin/banners/'.$card->id.'/edit')->assertForbidden();
    }
}
