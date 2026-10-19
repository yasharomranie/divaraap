<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use Modules\Category\Models\Category;
use Modules\Listing\Models\Listing;
use Modules\User\App\Models\User;
use Tests\TestCase;

final class ListingImprovementTest extends TestCase
{
    private function user(string $email = 'a@a.com'): User
    {
        return User::where('email', $email)->firstOrFail();
    }

    private function listing(array $data = []): Listing
    {
        return Listing::unguarded(fn () => Listing::create(array_replace(['title' => 'کوتاه', 'description' => '', 'slug' => (string) Str::uuid(), 'status' => 'pending', 'price' => null, 'currency' => 'IRR', 'user_id' => $this->user()->id, 'category_id' => Category::where('name', 'Phones')->firstOrFail()->id], $data)));
    }

    public function test_guide_is_owner_only_private_and_does_not_mutate_listing_or_count_views(): void
    {
        $listing = $this->listing(['view_count' => 12]);
        $before = $listing->fresh()->getAttributes();
        $url = route('panel.listings.improve', $listing);
        $this->get($url)->assertRedirect(route('login'));
        $this->actingAs($this->user('b@b.com'))->get($url)->assertForbidden();
        $this->actingAs($this->user())->withSession(['locale' => 'fa'])->get($url)->assertOk()
            ->assertHeader('Cache-Control', 'no-store, private')->assertHeader('X-Robots-Tag', 'noindex, nofollow')
            ->assertViewHas('guide', fn ($guide) => $guide['completed'] === 0 && $guide['total'] === 5)
            ->assertSee('راهنمای بهبود آگهی')->assertSee('اصلاح این بخش');
        $this->assertSame($before, $listing->fresh()->getAttributes());
        $listing->delete();
        $this->get($url)->assertNotFound();
    }

    public function test_saved_content_free_price_and_legacy_photo_complete_the_guide_without_private_contact_or_map(): void
    {
        $listing = $this->listing(['title' => 'گوشی سامسونگ مدل جدید سالم', 'description' => str_repeat('وضعیت کالا سالم و تمیز همراه با لوازم اصلی است. ', 5), 'price' => 0, 'country' => 'Iran', 'city' => 'Tehran', 'images' => ['images/sample.jpg']]);
        $guide = $listing->improvementGuide();
        $this->assertSame(5, $guide['completed']);
        $this->assertSame(1, $guide['photo_count']);
        $this->assertNull($listing->latitude);
        $this->assertNull($listing->contact_phone);
        $this->actingAs($this->user())->withSession(['locale' => 'fa'])->get(route('panel.listings.improve', $listing))
            ->assertOk()->assertSee('هر پنج معیار اطلاعاتی تکمیل شده‌اند.')->assertDontSee('aria-label="اصلاح این بخش:', false);
    }

    public function test_markup_punctuation_controls_and_placeholder_location_do_not_pad_checks(): void
    {
        $listing = $this->listing(['title' => str_repeat('!', 25), 'description' => str_repeat('<br>&nbsp;!', 90), 'country' => 'Iran', 'city' => 'Other']);
        $this->assertSame(0, $listing->improvementGuide()['completed']);
        $listing->forceFill(['title' => str_repeat('الف ', 25), 'description' => str_repeat("\u{200c}", 100)]);
        $this->assertFalse($listing->improvementGuide()['items'][0]['complete']);
        $this->assertFalse($listing->improvementGuide()['items'][1]['complete']);
        $listing->forceFill(['title' => '<script>alert(1)</script>'])->save();
        $this->actingAs($this->user())->get(route('panel.listings.improve', $listing))->assertOk()->assertDontSee('<script>alert(1)</script>', false);
    }

    public function test_fix_links_target_real_edit_fields_and_saved_changes_refresh_checks(): void
    {
        $listing = $this->listing();
        $this->actingAs($this->user())->withSession(['locale' => 'fa']);
        $response = $this->get(route('panel.listings.improve', $listing))->assertOk();
        foreach (['title', 'description', 'price', 'city', 'listing-images'] as $anchor) {
            $response->assertSee(route('panel.listings.edit', $listing).'#'.$anchor, false);
            $this->get(route('panel.listings.edit', $listing))->assertOk()->assertSee('id="'.$anchor.'"', false);
        }
        $this->put(route('panel.listings.update', $listing), ['title' => 'عنوان کامل و روشن کالای آزمایشی', 'description' => str_repeat('توضیحات واقعی کالا و شرایط معامله. ', 5), 'price' => 1000, 'country' => 'Iran', 'city' => 'Tehran', 'status' => 'active'])->assertRedirect();
        $this->assertSame('pending', $listing->fresh()->statusValue());
        $this->get(route('panel.listings.improve', $listing))->assertOk()->assertViewHas('guide', fn ($guide) => $guide['completed'] === 4);
    }

    public function test_owner_photo_upload_updates_check_and_preserves_legacy_gallery_and_moderation(): void
    {
        $listing = $this->listing(['status' => 'rejected', 'images' => ['images/legacy.jpg']]);
        $this->actingAs($this->user())->post(route('panel.listings.photos.store', $listing), ['photo' => UploadedFile::fake()->image('photo.jpg')])
            ->assertRedirect(route('panel.listings.improve', $listing))->assertSessionHas('success');
        $fresh = $listing->fresh();
        $this->assertSame('rejected', $fresh->statusValue());
        $this->assertCount(1, $fresh->getMedia('listing-images'));
        $this->assertCount(2, $fresh->galleryImageData());
        $this->assertContains('images/legacy.jpg', array_column(array_column($fresh->galleryImageData(), 'gallery'), 'fallback'));
        $this->assertTrue($fresh->improvementGuide()['items'][4]['complete']);
        $this->assertMatchesRegularExpression('/^[0-9a-f-]{36}\.jpg$/', $fresh->getFirstMedia('listing-images')->file_name);
        $fresh->clearMediaCollection('listing-images');
    }

    public function test_photo_upload_rejects_unauthorized_invalid_oversized_and_over_limit_requests(): void
    {
        $listing = $this->listing();
        $url = route('panel.listings.photos.store', $listing);
        $this->post($url, ['photo' => UploadedFile::fake()->image('photo.jpg')])->assertRedirect(route('login'));
        $this->actingAs($this->user('b@b.com'))->post($url, ['photo' => UploadedFile::fake()->image('photo.jpg')])->assertForbidden();
        $this->actingAs($this->user());
        foreach ([UploadedFile::fake()->create('bad.svg', 2, 'image/svg+xml'), UploadedFile::fake()->image('large.jpg')->size(6000)] as $file) {
            $this->post($url, ['photo' => $file])->assertRedirect(route('panel.listings.edit', $listing).'#listing-images')->assertSessionHasErrors('photo');
        }
        config(['quick-listing.max_photo_count' => 0]);
        $this->post($url, ['photo' => UploadedFile::fake()->image('photo.jpg')])->assertSessionHasErrors('photo');
        $this->assertCount(0, $listing->fresh()->getMedia('listing-images'));
    }
}
