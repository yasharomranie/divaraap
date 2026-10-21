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
use Modules\Video\Enums\VideoStatus;
use Modules\Video\Models\Video;
use Tests\TestCase;

final class OptionalVideoAdvertisingTest extends TestCase
{
    private User $seller;

    private Listing $listing;

    private Video $video;

    protected function setUp(): void
    {
        parent::setUp();
        config(['video.processing' => 'direct', 'video.disk' => 'public']);
        Storage::fake('public');
        $image = UploadedFile::fake()->image('poster.jpg');
        Storage::disk('public')->put('banners/poster.jpg', file_get_contents($image->getRealPath()));
        $this->seller = User::factory()->create(['name' => 'PRIVATE VIDEO OWNER', 'status' => 'active']);
        $this->listing = Listing::unguarded(fn () => Listing::create(['title' => 'Video listing fixture', 'description' => 'Public fixture', 'price' => 100000, 'currency' => 'IRR', 'user_id' => $this->seller->id, 'category_id' => Category::firstOrFail()->id, 'slug' => (string) Str::uuid(), 'status' => 'active']));
        $file = new UploadedFile(base_path('tests/Fixtures/advertising-video.mp4'), 'clip.mp4', 'video/mp4', test: true);
        $this->video = Video::createFromUploadedFile($this->listing, $file, ['title' => 'Public video fixture']);
    }

    private function card(array $data = []): Banner
    {
        $card = Banner::create(array_replace(['title' => 'Optional video fixture', 'alt' => 'Video poster', 'image' => 'banners/poster.jpg', 'creative_format' => 'video', 'video_id' => $this->video->id, 'video_transcript' => 'The clip shows the advertised product without spoken audio.', 'device' => 'both', 'is_active' => true, 'review_status' => 'approved'], $data));
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

    public function test_existing_ready_clip_renders_native_opt_in_player_separate_listing_link_and_text(): void
    {
        $card = $this->card(['url' => 'https://unrelated.example']);
        $this->assertSame(VideoStatus::Ready, $this->video->fresh()->status);
        $this->assertSame(route('listings.show', $this->listing), $card->destination());
        $this->get('/')->assertOk()->assertSee('data-ad-video-card="'.$card->id.'"', false)->assertSee('controls playsinline preload="none"', false)->assertSee($card->video_transcript)->assertSee(route('listings.show', $this->listing))->assertDontSee('autoplay', false)->assertDontSee('PRIVATE VIDEO OWNER')->assertDontSee($this->seller->email)->assertDontSee('https://unrelated.example');
        $this->assertArrayHasKey($this->video->id, Video::advertisingOptions('Public video'));
        $this->assertSame([], Video::advertisingClips([]));
    }

    public function test_pending_failed_inactive_or_missing_file_cannot_be_delivered(): void
    {
        $card = $this->card();
        foreach ([VideoStatus::Pending, VideoStatus::Processing, VideoStatus::Failed] as $status) {
            $this->video->forceFill(['status' => $status])->saveQuietly();
            $this->assertFalse($card->fresh()->videoCreativeEligible());
        }
        $this->video->forceFill(['status' => VideoStatus::Ready, 'is_active' => false])->saveQuietly();
        $this->assertSame([], (new BannerRenderer)->at('home-top'));
        $this->video->forceFill(['is_active' => true])->saveQuietly();
        Storage::disk('public')->delete($this->video->path);
        $this->assertNull($card->fresh()->advertisedVideo());
        $this->assertContains('video_unavailable', array_column(Banner::healthIssues(), 'code'));
        $card->update(['is_active' => false]);
        $this->assertFalse($card->fresh()->is_active);
    }

    public function test_unavailable_listing_owner_or_owner_mismatch_suppresses_clip(): void
    {
        $card = $this->card();
        $this->listing->forceFill(['status' => 'sold'])->save();
        $this->assertNull($card->fresh()->destination());
        $this->listing->forceFill(['status' => 'active'])->save();
        $this->seller->update(['status' => 'suspended']);
        $this->assertFalse($card->fresh()->videoCreativeEligible());
        $this->seller->update(['status' => 'active']);
        $this->listing->forceFill(['user_id' => User::factory()->create(['status' => 'active'])->id])->save();
        $this->assertSame([], Video::advertisingClips([$this->video->id]));
    }

    public function test_bad_paths_wrong_disk_or_mime_and_oversized_assets_are_rejected(): void
    {
        foreach ([['path' => '../outside.mp4'], ['path' => 'https://external.example/clip.mp4'], ['disk' => 'local'], ['mime_type' => 'video/webm']] as $data) {
            $clip = clone $this->video;
            $clip->forceFill($data)->saveQuietly();
            $this->assertSame([], Video::advertisingClips([$clip->id]));
            $this->video->fresh()->forceFill(['path' => $this->video->path, 'disk' => $this->video->disk, 'mime_type' => $this->video->mime_type])->saveQuietly();
        }
        Storage::disk('public')->put('videos/fake.mp4', '<html>not video</html>');
        $this->video->forceFill(['path' => 'videos/fake.mp4'])->saveQuietly();
        $this->assertSame([], Video::advertisingClips([$this->video->id]));
        Storage::disk('public')->put('videos/large.mp4', str_repeat('x', 20 * 1024 * 1024 + 1));
        $this->video->forceFill(['path' => 'videos/large.mp4'])->saveQuietly();
        $this->assertSame([], Video::advertisingClips([$this->video->id]));
    }

    public function test_transcript_is_required_bounded_and_escaped(): void
    {
        foreach (['', str_repeat('x', 2001)] as $text) {
            try {
                $this->card(['video_transcript' => $text]);
                $this->fail('Invalid transcript accepted');
            } catch (ValidationException $exception) {
                $this->assertArrayHasKey('video_transcript', $exception->errors());
            }
        }
        $card = $this->card(['video_transcript' => '<script>transcript()</script>']);
        $this->get('/')->assertOk()->assertSee('&lt;script&gt;transcript()&lt;/script&gt;', false)->assertDontSee('<script>transcript()</script>', false);
        $this->assertTrue($card->videoCreativeEligible());
    }

    public function test_local_webvtt_and_language_are_validated_and_missing_caption_hides_card(): void
    {
        Storage::disk('public')->put('banners/captions.vtt', "WEBVTT\n\n00:00.000 --> 00:04.000\nExample caption\n");
        $card = $this->card(['video_captions' => 'banners/captions.vtt', 'video_caption_language' => 'fa']);
        $this->get('/')->assertOk()->assertSee('kind="captions"', false)->assertSee('srclang="fa"', false);
        foreach ([['video_captions' => '../private.vtt'], ['video_captions' => 'https://example.com/file.vtt'], ['video_caption_language' => 'xx']] as $data) {
            try {
                $this->card(array_replace(['video_captions' => 'banners/captions.vtt', 'video_caption_language' => 'fa'], $data));
                $this->fail('Invalid captions accepted');
            } catch (ValidationException $exception) {
                $this->assertArrayHasKey('video_captions', $exception->errors());
            }
        }
        Storage::disk('public')->put('banners/captions.vtt', 'not a WebVTT document');
        $this->assertFalse($card->fresh()->videoCreativeEligible());
        $card->update(['is_active' => false]);
        $this->assertFalse($card->fresh()->is_active);
    }

    public function test_caption_endpoint_serves_webvtt_and_rejects_stale_or_unpublished_sources(): void
    {
        Storage::disk('public')->put('banners/endpoint.vtt', "WEBVTT\n\n00:00.000 --> 00:04.000\nExample caption\n");
        $card = $this->card(['video_captions' => 'banners/endpoint.vtt', 'video_caption_language' => 'fa']);
        $url = route('advertising.video-captions', ['banner' => $card->id, 'version' => $card->videoSignature()]);
        $this->get($url)->assertOk()->assertHeader('Content-Type', 'text/vtt; charset=UTF-8')->assertHeader('X-Content-Type-Options', 'nosniff')->assertSee('Example caption');
        $this->get(route('advertising.video-captions', ['banner' => $card->id, 'version' => 'bad']))->assertNotFound();
        $card->update(['video_transcript' => 'Changed public transcript']);
        $this->get($url)->assertNotFound();
        $card->update(['is_active' => false]);
        $this->get(route('advertising.video-captions', ['banner' => $card->id, 'version' => $card->videoSignature()]))->assertNotFound();
    }

    public function test_source_changes_require_snapshot_refresh_and_staff_review(): void
    {
        $card = $this->card();
        $staff = User::factory()->create(['status' => 'active']);
        $staff->syncRoles(['advertising-manager']);
        $this->actingAs($staff);
        $this->video->update(['title' => 'Changed public clip title']);
        $this->assertFalse($card->fresh()->videoCreativeEligible());
        $card->save();
        $this->assertSame('pending', $card->fresh()->review_status);
        $this->assertFalse($card->fresh()->is_active);
        $staff->syncRoles(['moderator']);
        Banner::moderationQueue()->firstWhere('id', $card->id)->moderate(true);
        $this->assertSame($this->video->id, (int) $card->fresh()->video_id);
        $this->assertTrue($card->fresh()->videoCreativeEligible());
    }

    public function test_old_token_cannot_count_refreshed_video_content(): void
    {
        $card = $this->card();
        $token = $this->token($card);
        $this->video->update(['title' => 'Changed clip title']);
        $card->save();
        $this->travel(2)->seconds();
        foreach (['click', 'impression'] as $kind) {
            $this->withCredentials()->postJson(route('advertising.events'), ['token' => $token, 'kind' => $kind, 'device' => 'mobile'])->assertNoContent();
        }
        $this->assertSame(0, AdvertisingEvent::where('banner_id', $card->id)->count());
        $this->travelBack();
    }

    public function test_poster_impression_and_listing_click_deduplicate_without_fake_play_metrics(): void
    {
        $card = $this->card();
        $this->actingAs(User::factory()->create(['status' => 'active']));
        $token = $this->token($card);
        $this->travel(2)->seconds();
        $this->withCredentials()->postJson(route('advertising.events'), ['token' => $token, 'kind' => 'impression', 'device' => 'mobile'])->assertNoContent();
        $response = $this->withCredentials()->postJson(route('advertising.events'), ['token' => $token, 'kind' => 'click', 'device' => 'mobile'])->assertNoContent();
        $this->withCredentials()->postJson(route('advertising.events'), ['token' => $token, 'kind' => 'click', 'device' => 'mobile'])->assertNoContent();
        $this->withCredentials()->postJson(route('advertising.events'), ['token' => $token, 'kind' => 'play', 'device' => 'mobile'])->assertUnprocessable();
        $this->assertSame(2, AdvertisingEvent::where('banner_id', $card->id)->count());
        $this->withCookie('advertising_attribution', $response->getCookie('advertising_attribution')->getValue());
        $this->withCredentials()->postJson(route('conversations.start', $this->listing), ['message' => 'Is this available?'])->assertSuccessful();
        $this->assertSame(1, AdvertisingEvent::where('banner_id', $card->id)->where('kind', 'conversion')->count());
        $this->travelBack();
    }

    public function test_campaign_device_capacity_and_preview_rules_are_reused(): void
    {
        $campaign = Campaign::create(['title' => 'Video targeting', 'is_active' => true, 'page_paths' => ['/allowed']]);
        $card = $this->card(['campaign_id' => $campaign->id, 'device' => 'mobile']);
        $this->assertFalse($card->eligibleForCampaign(['path' => 'other']));
        $result = (new BannerRenderer)->at('home-top', context: ['path' => 'allowed']);
        $this->assertSame($card->id, $result['mobile']->id);
        $this->assertArrayNotHasKey('desktop', $result);
        $this->assertFalse($card->eligibleForPlacement('category-top'));
        $this->actingAs(User::where('email', 'a@a.com')->firstOrFail());
        $this->get(route('advertising.preview-render', ['banner' => $card->id, 'device' => 'mobile']))->assertOk()->assertSee('data-ad-video-card=', false)->assertDontSee('<video', false)->assertDontSee($this->video->playableUrl())->assertDontSee('data-event-token=', false)->assertDontSee(route('listings.show', $this->listing));
    }

    public function test_filament_save_preserves_video_caption_language_and_plain_users_cannot_edit(): void
    {
        Storage::disk('public')->put('banners/filament.vtt', "WEBVTT\n\n00:00.000 --> 00:04.000\nExample caption\n");
        $card = $this->card(['video_captions' => 'banners/filament.vtt', 'video_caption_language' => 'en']);
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $this->actingAs(User::where('email', 'a@a.com')->firstOrFail());
        Livewire::test(EditBanner::class, ['record' => $card->id])->call('save')->assertHasNoFormErrors();
        $this->assertSame('video', $card->fresh()->creative_format);
        $this->assertSame('en', $card->fresh()->video_caption_language);
        $this->actingAs($this->seller)->get('/admin/banners/'.$card->id.'/edit')->assertForbidden();
    }
}
