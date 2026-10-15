<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Modules\Listing\Models\Listing;
use Modules\Panel\App\Livewire\PanelQuickListingForm;
use Modules\User\App\Models\Profile;
use Modules\User\App\Models\User;
use Modules\Video\Models\Video;
use Tests\TestCase;

class ReleaseReadinessTest extends TestCase
{
    public function test_registration_form_and_request_agree_and_phone_is_saved(): void
    {
        $this->withSession(['locale' => 'fa'])->get('/register')->assertSuccessful()
            ->assertSee('name="first_name"', false)->assertSee('name="last_name"', false)->assertSee('name="terms"', false);
        $this->post('/register', ['first_name' => 'کاربر', 'last_name' => 'آزمون', 'email' => 'release-register@example.test', 'phone' => '۰۹۱۲۳۴۵۶۷۸۹', 'password' => 'ReleasePassword2026!', 'password_confirmation' => 'ReleasePassword2026!', 'terms' => '1'])
            ->assertSessionHasNoErrors()->assertRedirect(route('dashboard', absolute: false));
        $user = User::query()->where('email', 'release-register@example.test')->firstOrFail();
        $this->assertAuthenticatedAs($user);
        $this->assertSame('کاربر آزمون', $user->name);
        $this->assertTrue(Hash::check('ReleasePassword2026!', $user->password));
        $this->assertSame('+989123456789', Profile::phoneForUser($user));
    }

    public function test_registration_rejects_missing_terms_wrong_confirmation_and_invalid_phone(): void
    {
        $this->withSession(['locale' => 'fa'])->from('/register')->post('/register', ['first_name' => 'کاربر', 'last_name' => 'آزمون', 'email' => 'release-rejected@example.test', 'phone' => '۰۹۱۲', 'password' => 'ReleasePassword2026!', 'password_confirmation' => 'different'])
            ->assertRedirect('/register')->assertSessionHasErrors(['terms', 'password', 'phone']);
        $this->assertFalse(User::query()->where('email', 'release-rejected@example.test')->exists());
    }

    public function test_bad_numeric_route_identifiers_return_404_instead_of_database_errors(): void
    {
        foreach (['not-a-number', '9223372036854775808', '999999999999999999999999', '0'] as $identifier) {
            $this->get('/listings/'.$identifier)->assertNotFound();
            $this->get('/sellers/'.$identifier)->assertNotFound();
        }
        $listing = Listing::query()->active()->firstOrFail();
        $this->get(route('listings.show', $listing))->assertSuccessful();
    }

    public function test_video_preview_and_modal_translate_empty_states_and_labels(): void
    {
        app()->setLocale('fa');
        $video = new Video;
        $preview = view('video::filament.video-preview-field', ['video' => null])->render();
        $this->assertStringContainsString('پیش‌نمایش پس از نخستین بارگذاری', $preview);
        $this->assertStringNotContainsString('Preview will appear', $preview);
        $modal = view('video::filament.video-player', ['video' => $video])->render();
        $this->assertStringContainsString('این ویدیو هنوز فایل قابل پخش ندارد.', $modal);
        $this->assertStringContainsString('وضوح', $modal);
        $this->assertStringNotContainsString('>Resolution<', $modal);
        $this->assertSame('ویدیو شمارهٔ 0', $video->titleLabel());
        app()->setLocale('en');
        $this->assertSame('Video #0', $video->titleLabel());
        $this->assertStringContainsString('Preview will appear', view('video::filament.video-preview-field', ['video' => null])->render());
    }

    public function test_wizard_accepts_a_video_above_the_old_temporary_upload_limit(): void
    {
        Storage::fake('local');
        $this->actingAs(User::query()->where('email', 'b@b.com')->firstOrFail());
        Livewire::test(PanelQuickListingForm::class)
            ->set('videos', [UploadedFile::fake()->create('release-large.mp4', 20000, 'video/mp4')])
            ->assertHasNoErrors(['videos'])
            ->assertCount('videos', 1);
    }

    public function test_published_video_does_not_break_public_listing_details(): void
    {
        $listing = Listing::query()->active()->firstOrFail();
        $video = Video::query()->create(['listing_id' => $listing->id, 'user_id' => $listing->user_id, 'title' => 'Release video', 'is_active' => true]);
        $video->forceFill(['path' => 'videos/release-test.mp4', 'disk' => 'public', 'status' => 'ready'])->save();
        $this->withSession(['locale' => 'fa'])->get(route('listings.show', $listing))->assertSuccessful()->assertSee('videos/release-test.mp4', false);
    }

    public function test_skip_link_targets_focusable_main_content_in_both_languages(): void
    {
        foreach (['fa' => 'رفتن به محتوای اصلی', 'en' => 'Skip to main content'] as $locale => $text) {
            $this->withSession(['locale' => $locale])->get('/')->assertSuccessful()
                ->assertSee('href="#main-content"', false)->assertSee('id="main-content" tabindex="-1"', false)->assertSee($text);
        }
    }
}
