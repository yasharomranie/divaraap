<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Modules\Category\Models\Category;
use Modules\Listing\Models\Listing;
use Modules\Listing\Models\ListingDraft;
use Modules\Listing\Support\ListingPanelHelper;
use Modules\Location\Models\Country;
use Modules\Panel\App\Livewire\PanelQuickListingForm;
use Modules\User\App\Models\User;
use Tests\TestCase;

final class ListingDuplicateTest extends TestCase
{
    private function user(): User
    {
        return User::where('email', 'a@a.com')->firstOrFail();
    }

    private function listing(array $overrides = []): Listing
    {
        return Listing::unguarded(fn () => Listing::create(array_replace(['title' => 'کالای نمونه برای ساخت مشابه', 'description' => 'توضیح واقعی کالای نمونه', 'slug' => (string) Str::uuid(), 'user_id' => $this->user()->id, 'category_id' => Category::where('name', 'Phones')->firstOrFail()->id, 'status' => 'sold', 'price' => 1000, 'currency' => ListingPanelHelper::defaultCurrency(), 'is_featured' => true, 'view_count' => 70, 'latitude' => 35.7234567, 'longitude' => 51.4245678, 'expires_at' => now()->subDay()], $overrides)));
    }

    private function copy(Listing $listing, array $data = [])
    {
        return $this->post(route('panel.listings.duplicate.store', $listing), $data + ['source_version' => $listing->fresh()->duplicationVersion()]);
    }

    public function test_review_is_read_only_and_copy_creates_only_a_private_draft(): void
    {
        $listing = $this->listing()->fresh();
        $original = $listing->getAttributes();
        $before = Listing::count();
        $this->actingAs($this->user())->get(route('panel.listings.duplicate', $listing))->assertOk()->assertHeader('Cache-Control', 'no-store, private')->assertSee($listing->title);
        $this->assertNull(ListingDraft::currentFor($this->user()));
        $this->copy($listing)->assertRedirect(route('panel.listings.create'));
        $draft = ListingDraft::currentFor($this->user());
        $this->assertSame($listing->title, $draft->payload['listingTitle']);
        $this->assertSame('', $draft->payload['latitude']);
        $this->assertSame([], $draft->files);
        foreach (['status', 'is_featured', 'view_count', 'expires_at', 'slug', 'user_id', 'contact_phone'] as $key) {
            $this->assertArrayNotHasKey($key, $draft->payload);
        }
        $this->assertSame($before, Listing::count());
        $this->assertSame($original, $listing->fresh()->getAttributes());
    }

    public function test_existing_durable_and_legacy_session_drafts_are_never_overwritten(): void
    {
        $listing = $this->listing();
        $this->actingAs($this->user());
        $draft = ListingDraft::saveFor($this->user(), null, 0, ['listingTitle' => 'پیش‌نویس مهم']);
        $this->copy($listing)->assertSessionHasErrors('draft');
        $this->assertSame('پیش‌نویس مهم', $draft->fresh()->payload['listingTitle']);
        ListingDraft::consume($this->user(), $draft->id, $draft->version);
        session()->put('panel_quick_listing_draft.'.$this->user()->id, ['listingTitle' => 'نشست قبلی']);
        $this->copy($listing)->assertSessionHasErrors('draft');
        $this->assertNull(ListingDraft::currentFor($this->user()));
        $this->get(route('panel.listings.duplicate', $listing))->assertSee(__('listing::duplicate.continue'))->assertDontSee('name="source_version"', false);
    }

    public function test_copy_is_owner_only_and_rejects_guest_inactive_deleted_and_invalid_source(): void
    {
        $listing = $this->listing();
        $this->get(route('panel.listings.duplicate', $listing))->assertRedirect();
        $other = User::whereKeyNot($this->user()->id)->firstOrFail();
        $this->actingAs($other)->get(route('panel.listings.duplicate', $listing))->assertForbidden();
        $this->copy($listing)->assertForbidden();
        $user = $this->user();
        $user->forceFill(['status' => 'suspended'])->saveQuietly();
        $this->actingAs($user);
        $this->copy($listing)->assertForbidden();
        $listing->delete();
        $this->get(route('panel.listings.duplicate', $listing))->assertNotFound();
        $this->post('/panel/my-listings/999999999999999999999999/duplicate')->assertNotFound();
    }

    public function test_changed_source_and_double_submit_do_not_produce_duplicate_copies(): void
    {
        $listing = $this->listing();
        $version = $listing->duplicationVersion();
        $listing->forceFill(['title' => 'عنوان تغییر کرده'])->save();
        $this->actingAs($this->user());
        $this->copy($listing, ['source_version' => $version])->assertSessionHasErrors('source');
        $this->assertNull(ListingDraft::currentFor($this->user()));
        $this->copy($listing)->assertRedirect();
        $this->copy($listing)->assertSessionHasErrors('draft');
        $this->assertSame(1, ListingDraft::count());
    }

    public function test_copy_filters_retired_fields_category_and_foreign_currency_and_bounds_text(): void
    {
        $listing = $this->listing(['currency' => 'USD', 'title' => str_repeat('آ', 100), 'description' => str_repeat('ب', 2000), 'custom_fields' => ['unknown_private_field' => 'secret']]);
        $listing->category->forceFill(['is_active' => false])->save();
        $payload = $listing->fresh()->duplicationPayload(false);
        $this->assertNull($payload['selectedCategoryId']);
        $this->assertSame([], $payload['customFieldValues']);
        $this->assertSame('', $payload['price']);
        $this->assertSame(70, mb_strlen($payload['listingTitle']));
        $this->assertSame(1450, mb_strlen($payload['description']));
    }

    public function test_optional_photos_are_independent_private_copies_and_map_requires_opt_in(): void
    {
        Storage::fake('local');
        Storage::fake('public');
        $listing = $this->listing();
        $image = UploadedFile::fake()->image('original.jpg');
        $listing->attachListingImage($image->getRealPath(), 'original.jpg', 'public');
        $listing->refresh();
        $media = $listing->getFirstMedia('listing-images');
        $originalPath = $media->getPath();
        $this->actingAs($this->user());
        $this->copy($listing, ['photos' => 1, 'location' => 1])->assertRedirect();
        $draft = ListingDraft::currentFor($this->user());
        $this->assertCount(1, $draft->files);
        $this->assertSame('35.723', $draft->payload['latitude']);
        $this->assertSame('51.425', $draft->payload['longitude']);
        $path = array_values($draft->files)[0]['path'];
        Storage::disk('local')->assertExists($path);
        $this->assertFileExists($originalPath);
        ListingDraft::consume($this->user(), $draft->id, $draft->version);
        Storage::disk('local')->assertMissing($path);
        $this->assertFileExists($originalPath);
        $this->assertSame(1, $listing->fresh()->getMedia('listing-images')->count());
    }

    public function test_new_listing_from_copied_draft_goes_through_pending_moderation(): void
    {
        Storage::fake('local');
        Storage::fake('public');
        $listing = $this->listing(['country' => Country::where('is_active', true)->firstOrFail()->name]);
        $image = UploadedFile::fake()->image('copy.jpg');
        $listing->attachListingImage($image->getRealPath(), 'copy.jpg', 'public');
        $listing->refresh();
        $this->actingAs($this->user());
        $this->copy($listing, ['photos' => 1])->assertRedirect();
        Livewire::test(PanelQuickListingForm::class)->assertSet('listingTitle', $listing->title)->call('goToCategoryStep')->assertSet('selectedCategoryId', $listing->category_id)->assertSet('detectedError', null)->call('publishListing')->assertHasNoErrors()->assertRedirect();
        $fresh = Listing::latest('id')->firstOrFail();
        $this->assertNotSame($listing->id, $fresh->id);
        $this->assertNotSame($listing->slug, $fresh->slug);
        $this->assertSame('pending', $fresh->statusValue());
        $this->assertFalse($fresh->is_featured);
        $this->assertSame(0, $fresh->view_count);
        $this->assertSame('sold', $listing->fresh()->statusValue());
        $this->assertNull(ListingDraft::currentFor($this->user()));
    }

    public function test_linked_legacy_photos_are_not_fetched_and_failed_copy_keeps_source_unchanged(): void
    {
        Http::preventStrayRequests();
        $listing = $this->listing(['images' => ['https://example.test/remote.jpg']])->fresh();
        $original = $listing->getAttributes();
        $this->actingAs($this->user());
        $this->copy($listing, ['photos' => 1])->assertSessionHasErrors('photos');
        $this->assertNull(ListingDraft::currentFor($this->user()));
        $this->assertSame($original, $listing->fresh()->getAttributes());
    }

    public function test_unrelated_view_count_changes_do_not_invalidate_reviewed_content(): void
    {
        $listing = $this->listing()->fresh();
        $version = $listing->duplicationVersion();
        $listing->trackViewBy(999999);
        $this->actingAs($this->user());
        $this->copy($listing, ['source_version' => $version])->assertRedirect(route('panel.listings.create'));
        $this->assertSame($listing->title, ListingDraft::currentFor($this->user())->payload['listingTitle']);
    }
}
