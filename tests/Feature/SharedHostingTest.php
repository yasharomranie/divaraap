<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Providers\AppServiceProvider;
use Illuminate\Database\QueryException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Modules\Conversation\App\Models\Conversation;
use Modules\Listing\Models\Listing;
use Modules\User\App\Models\User;
use Modules\Video\Enums\VideoStatus;
use Modules\Video\Jobs\ProcessVideo;
use Modules\Video\Models\Video;
use Tests\TestCase;

class SharedHostingTest extends TestCase
{
    public function test_shared_profile_avoids_daemons_and_shell_optimizers(): void
    {
        config(['hosting.profile' => 'shared']);
        (new AppServiceProvider($this->app))->register();
        $this->assertSame('sync', config('queue.default'));
        $this->assertSame('null', config('broadcasting.default'));
        $this->assertFalse(config('demo.enabled'));
        $this->assertSame('direct', config('video.processing'));
        $this->assertSame([], config('media-library.image_optimizers'));
        $this->assertSame('gd', config('media-library.image_driver'));
    }

    public function test_soft_deleted_unique_values_can_be_reused_and_active_duplicates_are_rejected(): void
    {
        $email = 'shared-unique@example.test';
        $user = User::query()->create(['name' => 'First', 'email' => $email, 'password' => 'SharedPassword2026!']);
        $user->delete();
        $replacement = User::query()->create(['name' => 'Second', 'email' => $email, 'password' => 'SharedPassword2026!']);
        $this->assertNotSame($user->getKey(), $replacement->getKey());
        $this->expectException(QueryException::class);
        User::query()->create(['name' => 'Duplicate', 'email' => $email, 'password' => 'SharedPassword2026!']);
    }

    public function test_mp4_upload_is_ready_without_processes_or_a_queue_worker(): void
    {
        config(['video.processing' => 'direct', 'video.disk' => 'public']);
        Storage::fake('public');
        Queue::fake();
        $listing = Listing::query()->firstOrFail();
        $file = new UploadedFile(base_path('tests/Fixtures/shared-hosting.mp4'), 'clip.mp4', 'video/mp4', null, true);
        $video = Video::createFromUploadedFile($listing, $file, ['disk' => 'public']);
        $this->assertSame($listing->title, Video::panelIndexDataForUser($listing->user)['listingOptions'][$listing->id]);
        $this->assertSame(VideoStatus::Ready, $video->fresh()->status);
        $this->assertNull($video->upload_path);
        $this->assertNotEmpty($video->path);
        Storage::disk('public')->assertExists($video->path);
        $this->assertNotNull($video->playableUrl());
        Queue::assertNotPushed(ProcessVideo::class);
    }

    public function test_replacing_a_direct_upload_removes_the_previous_file(): void
    {
        config(['video.processing' => 'direct', 'video.disk' => 'public']);
        Storage::fake('public');
        $listing = Listing::query()->firstOrFail();
        $upload = fn (): UploadedFile => new UploadedFile(base_path('tests/Fixtures/shared-hosting.mp4'), 'clip.mp4', 'video/mp4', null, true);
        $video = Video::createFromUploadedFile($listing, $upload(), ['disk' => 'public']);
        $previous = $video->path;
        $video->updateFromPanel(['video_file' => $upload(), 'is_active' => true]);
        $this->assertNotSame($previous, $video->path);
        $this->assertSame(VideoStatus::Ready, $video->fresh()->status);
        Storage::disk('public')->assertMissing($previous);
        Storage::disk('public')->assertExists($video->path);
    }

    public function test_direct_mode_rejects_unsupported_content_before_storing_it(): void
    {
        config(['video.processing' => 'direct']);
        Storage::fake('public');
        $count = Video::query()->count();
        try {
            Video::createFromUploadedFile(Listing::query()->firstOrFail(), UploadedFile::fake()->create('clip.webm', 20, 'video/webm'), ['disk' => 'public']);
            $this->fail('Unsupported video accepted.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('video_file', $exception->errors());
        }
        $this->assertSame($count, Video::query()->count());
        $this->assertSame([], Storage::disk('public')->allFiles());
    }

    public function test_polling_is_incremental_private_and_reports_unread_counts(): void
    {
        $listing = Listing::query()->whereNotNull('user_id')->firstOrFail();
        $buyer = User::query()->where('id', '!=', $listing->user_id)->firstOrFail();
        $conversation = Conversation::openForListingBuyer($listing, $buyer->id);
        $old = $conversation->createMessageFor((int) $listing->user_id, 'Earlier');
        $new = $conversation->createMessageFor((int) $listing->user_id, 'New Persian پیام');
        $this->actingAs($buyer)->getJson(route('conversations.updates', ['conversation' => $conversation->id, 'after' => $old->id]))
            ->assertOk()->assertJsonCount(1, 'messages')->assertJsonPath('messages.0.message.id', $new->id)
            ->assertJsonPath('messages.0.message.body', 'New Persian پیام')->assertHeader('Cache-Control', 'no-store, private');
        $outsider = User::query()->whereNotIn('id', [$listing->user_id, $buyer->id])->firstOrFail();
        $this->actingAs($outsider)->getJson(route('conversations.updates', ['conversation' => $conversation->id]))->assertForbidden();
        $this->actingAs($buyer)->getJson(route('conversations.updates', ['after' => '-1']))->assertUnprocessable();
    }

    public function test_shared_layout_advertises_polling_without_websocket_credentials(): void
    {
        config(['hosting.profile' => 'shared']);
        (new AppServiceProvider($this->app))->register();
        $this->actingAs(User::query()->firstOrFail())->get('/panel')
            ->assertOk()->assertSee('data-inbox-poll-url=', false)->assertDontSee('data-reverb-key=', false);
    }
}
