<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Modules\Category\Models\Category;
use Modules\Listing\Models\Listing;
use Modules\Listing\Models\ListingDraft;
use Modules\Listing\Support\DraftConflict;
use Modules\Location\Models\Country;
use Modules\Panel\App\Livewire\PanelQuickListingForm;
use Modules\User\App\Models\User;
use Modules\Video\Models\Video;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

final class ListingDraftTest extends TestCase
{
    private function user(): User
    {
        return User::where('email', 'a@a.com')->firstOrFail();
    }

    public function test_text_is_private_encrypted_and_restores_without_session_or_publication(): void
    {
        Storage::fake('local');
        $this->actingAs($this->user());
        $before = Listing::count();
        Livewire::test(PanelQuickListingForm::class)
            ->set('listingTitle', 'پیش‌نویس خصوصی گوشی')
            ->set('description', 'متن شخصی آزمایشی')
            ->set('latitude', '0')->set('longitude', '51.4')
            ->call('goToStep', 3)->call('saveDraft')
            ->assertSet('draftError', null);
        $draft = ListingDraft::currentFor($this->user());
        $this->assertNotNull($draft);
        $raw = DB::table('listing_drafts')->where('id', $draft->id)->value('payload');
        $this->assertStringNotContainsString('متن شخصی', $raw);
        session()->flush();
        Livewire::test(PanelQuickListingForm::class)
            ->assertSet('listingTitle', 'پیش‌نویس خصوصی گوشی')
            ->assertSet('currentStep', 3)->assertSet('latitude', '0')
            ->assertSet('longitude', '51.4');
        $this->assertSame($before, Listing::count());
    }

    public function test_empty_open_does_not_create_a_draft_and_session_payload_is_imported(): void
    {
        $this->actingAs($this->user());
        Livewire::test(PanelQuickListingForm::class);
        $this->assertNull(ListingDraft::currentFor($this->user()));
        session()->put('panel_quick_listing_draft.'.$this->user()->id, ['listingTitle' => 'پیش‌نویس قبلی', 'selectedCountryId' => null]);
        Livewire::test(PanelQuickListingForm::class)->assertSet('listingTitle', 'پیش‌نویس قبلی')->assertSet('selectedCountryId', null)->call('saveDraft');
        $this->assertSame('پیش‌نویس قبلی', ListingDraft::currentFor($this->user())->payload['listingTitle']);
        $this->assertFalse(session()->has('panel_quick_listing_draft.'.$this->user()->id));
    }

    public function test_uploaded_photo_survives_a_fresh_form_and_has_owner_only_preview(): void
    {
        Storage::fake('local');
        $this->actingAs($this->user());
        $component = Livewire::test(PanelQuickListingForm::class)->set('photos', [UploadedFile::fake()->image('private.png')])->call('saveDraft');
        $draft = ListingDraft::currentFor($this->user());
        $this->assertCount(1, $draft->files);
        $key = array_key_first($draft->files);
        Storage::disk('local')->assertExists($draft->files[$key]['path']);
        $component->set('description', 'تغییر دیگر')->call('saveDraft');
        $this->assertCount(1, $draft->fresh()->files);
        $this->assertArrayNotHasKey('path', $component->get('draftFiles')[$key]);
        session()->flush();
        $fresh = Livewire::test(PanelQuickListingForm::class)->assertSee('saved-photo-'.$key, false);
        $url = route('panel.drafts.photo', ['draft' => $draft->id, 'file' => $key]);
        $this->get($url)->assertOk()->assertHeader('X-Content-Type-Options', 'nosniff');
        $other = User::whereKeyNot($this->user()->id)->firstOrFail();
        $this->actingAs($other)->get($url)->assertNotFound();
        auth()->logout();
        $this->get($url)->assertRedirect();
        $this->actingAs($this->user());
        $fresh->call('removeSavedFile', $key)->call('saveDraft');
        Storage::disk('local')->assertMissing($draft->files[$key]['path']);
        $this->assertSame([], $draft->fresh()->files);
    }

    public function test_stale_device_cannot_overwrite_and_can_reload_latest_version(): void
    {
        $this->actingAs($this->user());
        $first = Livewire::test(PanelQuickListingForm::class)->set('listingTitle', 'اول');
        $second = Livewire::test(PanelQuickListingForm::class);
        $first->set('listingTitle', 'نسخه جدید');
        $second->set('listingTitle', 'نسخه قدیمی')->assertSet('draftConflict', true);
        $this->assertSame('نسخه جدید', ListingDraft::currentFor($this->user())->payload['listingTitle']);
        $second->call('reloadDraft')->assertSet('draftConflict', false)->assertSet('listingTitle', 'نسخه جدید');
    }

    public function test_discard_scrubs_private_data_and_old_version_cannot_revive_it(): void
    {
        Storage::fake('local');
        $this->actingAs($this->user());
        $first = Livewire::test(PanelQuickListingForm::class)->set('photos', [UploadedFile::fake()->image('remove.png')]);
        $old = Livewire::test(PanelQuickListingForm::class);
        $draft = ListingDraft::currentFor($this->user());
        $path = array_values($draft->files)[0]['path'];
        $first->call('discardDraft')->assertSet('draftId', null);
        $this->assertNull(ListingDraft::currentFor($this->user()));
        Storage::disk('local')->assertMissing($path);
        $this->assertSame([], ListingDraft::withTrashed()->findOrFail($draft->id)->payload);
        $old->set('listingTitle', 'کهنه')->assertSet('draftConflict', true);
        $this->assertNull(ListingDraft::currentFor($this->user()));
        $first->set('listingTitle', 'پیش‌نویس تازه');
        $this->assertSame('پیش‌نویس تازه', ListingDraft::currentFor($this->user())->payload['listingTitle']);
    }

    public function test_failed_publication_keeps_draft_and_successful_claim_cannot_be_replayed(): void
    {
        Storage::fake('local');
        $key = (string) Str::uuid();
        $draft = ListingDraft::saveFor($this->user(), null, 0, ['listingTitle' => 'نگهداری'], [$key => ['type' => 'photo', 'file' => UploadedFile::fake()->image('keep.jpg')]]);
        try {
            ListingDraft::consume($this->user(), $draft->id, $draft->version, function () {
                throw new \RuntimeException('Simulated failure');
            });
            $this->fail('Expected publication failure');
        } catch (\RuntimeException $e) {
            $this->assertSame('Simulated failure', $e->getMessage());
        }
        $this->assertNotNull($draft->fresh());
        Storage::disk('local')->assertExists($draft->files[$key]['path']);
        $this->assertSame('published', ListingDraft::consume($this->user(), $draft->id, $draft->version, fn () => 'published'));
        Storage::disk('local')->assertMissing($draft->files[$key]['path']);
        $this->expectException(DraftConflict::class);
        ListingDraft::consume($this->user(), $draft->id, $draft->version, fn () => 'duplicate');
    }

    public function test_invalid_files_and_foreign_draft_cannot_be_saved(): void
    {
        Storage::fake('local');
        $draft = ListingDraft::saveFor($this->user(), null, 0, ['listingTitle' => 'خصوصی']);
        $other = User::whereKeyNot($this->user()->id)->firstOrFail();
        try {
            ListingDraft::saveFor($other, $draft->id, $draft->version, ['listingTitle' => 'تغییر']);
            $this->fail('Expected conflict');
        } catch (DraftConflict) {
            $this->assertSame('خصوصی', $draft->fresh()->payload['listingTitle']);
        }
        $this->expectException(ValidationException::class);
        ListingDraft::saveFor($this->user(), $draft->id, $draft->version, [], [(string) Str::uuid() => ['type' => 'photo', 'file' => UploadedFile::fake()->create('script.php', 1, 'text/plain')]]);
    }

    public function test_resumed_photos_publish_once_with_pending_moderation(): void
    {
        Storage::fake('local');
        Storage::fake('public');
        $this->actingAs($this->user());
        config(['video.processing' => 'direct']);
        $category = Category::where('name', 'Phones')->firstOrFail();
        Livewire::test(PanelQuickListingForm::class)->set('photos', [UploadedFile::fake()->image('publish.png')])->set('videos', [UploadedFile::fake()->createWithContent('movie.mp4', file_get_contents(base_path('tests/Fixtures/shared-hosting.mp4')))->mimeType('video/mp4')])->assertHasNoErrors()->assertSet('draftError', null)->set('selectedCategoryId', $category->id)->set('listingTitle', 'گوشی پیش‌نویس آزمایشی')->set('description', 'توضیح آگهی آزمایشی برای بررسی ثبت پیش‌نویس')->set('price', '1000')->set('selectedCountryId', Country::where('is_active', true)->firstOrFail()->id)->call('saveDraft');
        $draft = ListingDraft::currentFor($this->user());
        session()->flush();
        $before = Listing::count();
        Livewire::test(PanelQuickListingForm::class)->call('publishListing')->assertHasNoErrors()->assertSet('publishError', null)->assertRedirect();
        $this->assertSame($before + 1, Listing::count());
        $listing = Listing::latest('id')->firstOrFail();
        $this->assertSame('pending', $listing->statusValue());
        $this->assertSame(1, $listing->getMedia('listing-images')->count());
        $this->assertSame(1, Video::where('listing_id', $listing->id)->count());
        $this->assertNull(ListingDraft::currentFor($this->user()));
        Storage::disk('local')->assertMissing(array_values($draft->files)[0]['path']);
    }

    public function test_video_is_saved_privately_and_capacity_failure_preserves_previous_files(): void
    {
        Storage::fake('local');
        config(['video.processing' => 'direct', 'quick-listing.max_photo_count' => 1]);
        $photo = (string) Str::uuid();
        $video = (string) Str::uuid();
        $draft = ListingDraft::saveFor($this->user(), null, 0, [], [
            $photo => ['type' => 'photo', 'file' => UploadedFile::fake()->image('one.png')],
            $video => ['type' => 'video', 'file' => new UploadedFile(base_path('tests/Fixtures/shared-hosting.mp4'), 'movie.mp4', 'video/mp4', null, true)],
        ]);
        $this->assertCount(2, $draft->files);
        Storage::disk('local')->assertExists($draft->files[$video]['path']);
        try {
            ListingDraft::saveFor($this->user(), $draft->id, $draft->version, [], [(string) Str::uuid() => ['type' => 'photo', 'file' => UploadedFile::fake()->image('two.png')]]);
            $this->fail('Expected capacity validation');
        } catch (ValidationException) {
            $this->assertCount(2, $draft->fresh()->files);
            $this->assertCount(2, Storage::disk('local')->allFiles('listing-drafts'));
        }
    }

    public function test_switched_account_and_inactive_owner_cannot_mutate_a_draft(): void
    {
        $this->actingAs($this->user());
        $component = Livewire::test(PanelQuickListingForm::class)->set('listingTitle', 'مالک اصلی');
        $other = User::whereKeyNot($this->user()->id)->firstOrFail();
        $this->actingAs($other);
        $component->call('saveDraft')->assertForbidden();
        $owner = $this->user();
        $owner->forceFill(['status' => 'suspended'])->saveQuietly();
        $this->expectException(HttpException::class);
        ListingDraft::saveFor($owner, null, 0, ['listingTitle' => 'غیرفعال']);
    }
}
