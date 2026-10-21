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
use Modules\Advertising\Support\AdvertisingToken;
use Modules\Page\Models\Page;
use Modules\User\App\Models\User;
use Tests\TestCase;

final class GuidanceContentAdvertisingTest extends TestCase
{
    private Page $guide;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
        $image = UploadedFile::fake()->image('guide.jpg');
        Storage::disk('public')->put('banners/guide.jpg', file_get_contents($image->getRealPath()));
        $this->guide = Page::create(['title' => 'Safe shopping guide', 'slug' => 'guide-'.Str::uuid(), 'excerpt' => 'Check the item before payment.', 'body' => 'Meet safely and inspect the item.', 'placement' => 'help', 'is_published' => true]);
    }

    private function card(array $data = []): Banner
    {
        $card = Banner::create(array_replace(['title' => 'Guide campaign', 'alt' => 'Shopping guide', 'image' => 'banners/guide.jpg', 'creative_format' => 'guide', 'guide_page_id' => $this->guide->id, 'device' => 'both', 'is_active' => true, 'review_status' => 'approved'], $data));
        BannerPlacement::where('key', 'home-top')->firstOrFail()->assignments()->create(['banner_id' => $card->id, 'priority' => 1]);

        return $card;
    }

    public function test_public_card_links_only_to_existing_guide_and_escapes_content(): void
    {
        $this->guide->update(['title' => '<script>alert(1)</script>', 'excerpt' => '<b>Helpful</b>']);
        $card = $this->card(['url' => 'https://unrelated.example']);
        $this->assertSame(route('pages.show', $this->guide->slug), $card->destination());
        $this->get('/')->assertOk()->assertSee('data-ad-guide="'.$this->guide->id.'"', false)->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false)->assertSee('Helpful')->assertDontSee('<script>alert(1)</script>', false)->assertDontSee('https://unrelated.example');
        $this->get('/pages/'.$this->guide->slug)->assertOk();
    }

    public function test_unpublished_legal_deleted_and_empty_pages_are_rejected(): void
    {
        foreach ([['is_published' => false], ['placement' => 'legal'], ['body' => ''], ['title' => ' '], ['slug' => ' ']] as $invalid) {
            $original = $this->guide->getAttributes();
            $this->guide->update($invalid);
            try {
                $this->card();
                $this->fail('Invalid guide accepted');
            } catch (ValidationException $exception) {
                $this->assertArrayHasKey('guide_page_id', $exception->errors());
            }
            $this->guide->setRawAttributes($original)->save();
            $this->guide->refresh();
        }
        $this->guide->delete();
        $this->expectException(ValidationException::class);
        $this->card();
    }

    public function test_each_public_content_change_hides_card_until_saved_again(): void
    {
        $card = $this->card();
        foreach (['title' => 'Changed title', 'excerpt' => 'Changed excerpt', 'body' => 'Changed body', 'slug' => 'changed-'.Str::uuid()] as $field => $value) {
            $this->guide->update([$field => $value]);
            $this->assertFalse($card->fresh()->guideEligible());
            $this->get('/')->assertDontSee('data-ad-guide="'.$this->guide->id.'"', false);
            $card = $card->fresh();
            $card->save();
            $this->assertTrue($card->fresh()->guideEligible());
        }
    }

    public function test_publication_changes_hide_existing_card_and_options(): void
    {
        $card = $this->card();
        $this->guide->update(['is_published' => false]);
        $this->assertFalse($card->fresh()->guideEligible());
        $this->assertArrayNotHasKey($this->guide->id, Page::advertisingGuideOptions());
        $this->get('/pages/'.$this->guide->slug)->assertNotFound();
        $this->guide->delete();
        $this->assertNull($card->fresh()->destination());
    }

    public function test_stale_tokens_cannot_charge_after_guide_changes_and_click_has_no_listing_conversion(): void
    {
        $card = $this->card();
        $this->get('/')->assertOk();
        $token = AdvertisingToken::issue($card, 'home-top', 'both', []);
        AdvertisingEvent::record($token, 'click', 'mobile');
        $event = AdvertisingEvent::where('banner_id', $card->id)->firstOrFail();
        $this->assertNull($event->target_listing_id);
        $this->guide->update(['body' => 'Updated safety guidance']);
        $card = $card->fresh();
        $card->save();
        $this->travel(2)->seconds();
        AdvertisingEvent::record($token, 'impression', 'mobile');
        $this->assertSame(1, AdvertisingEvent::where('banner_id', $card->id)->count());
        $fresh = AdvertisingToken::issue($card->fresh(), 'home-top', 'both', []);
        $this->travel(2)->seconds();
        AdvertisingEvent::record($fresh, 'impression', 'mobile');
        $this->assertSame(2, AdvertisingEvent::where('banner_id', $card->id)->count());
    }

    public function test_admin_can_save_guide_binding_through_existing_resource(): void
    {
        $admin = User::where('email', 'a@a.com')->firstOrFail();
        $this->actingAs($admin);
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $card = $this->card();
        Livewire::test(EditBanner::class, ['record' => $card->id])
            ->fillForm(['title' => 'Updated guide card'])
            ->call('save')->assertHasNoFormErrors();
        $this->assertSame('Updated guide card', $card->fresh()->title);
        $this->assertTrue($card->fresh()->guideEligible());
    }

    public function test_staff_refresh_requires_review_and_other_formats_clear_binding(): void
    {
        $card = $this->card();
        $staff = User::factory()->create();
        $staff->syncRoles([]);
        $this->actingAs($staff);
        $this->guide->update(['body' => 'Staff must review this change']);
        $card = $card->fresh();
        $card->save();
        $this->assertSame('pending', $card->review_status);
        $this->assertFalse($card->is_active);
        $card->update(['creative_format' => 'image']);
        $this->assertNull($card->guide_page_id);
        $this->assertNull($card->guide_signature);
    }
}
