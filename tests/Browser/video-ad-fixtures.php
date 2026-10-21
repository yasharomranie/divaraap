<?php

declare(strict_types=1);

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Modules\Advertising\Models\Banner;
use Modules\Advertising\Models\BannerPlacement;
use Modules\Category\Models\Category;
use Modules\Listing\Models\Listing;
use Modules\User\App\Models\User;
use Modules\Video\Models\Video;
use Spatie\Activitylog\Models\Activity;

require dirname(__DIR__, 2).'/vendor/autoload.php';
$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
if (app()->environment('production')) {
    throw new RuntimeException('Browser fixtures require development.');
}
$path = storage_path('framework/video-ad-review.json');
$action = $argv[1] ?? '';
if (in_array($action, ['cleanup', 'change-video'], true)) {
    $state = json_decode(file_get_contents($path), true, 32, JSON_THROW_ON_ERROR);
    if ($action === 'change-video') {
        Video::findOrFail($state['video'])->update(['title' => 'ویدئوی تغییرکرده آزمایش']);
        exit;
    }
    DB::transaction(function () use ($state): void {
        DB::table('advertising_assignments')->where('banner_id', $state['banner'])->delete();
        DB::table('advertising_events')->where('banner_id', $state['banner'])->delete();
        Banner::withTrashed()->whereKey($state['banner'])->forceDelete();
        Video::withTrashed()->findOrFail($state['video'])->forceDelete();
        Listing::withTrashed()->whereKey($state['listing'])->forceDelete();
        User::withTrashed()->whereKey($state['seller'])->forceDelete();
        foreach ([Banner::class => [$state['banner']], Listing::class => [$state['listing']], User::class => [$state['seller']]] as $class => $ids) {
            Activity::where('subject_type', $class)->whereIn('subject_id', $ids)->delete();
        }
    });
    Storage::disk('public')->delete([$state['image'], $state['captions']]);
    unlink($path);
    exit;
}
if (is_file($path)) {
    throw new RuntimeException('Clean previous fixtures first.');
}
config(['video.processing' => 'direct', 'video.disk' => 'public']);
$seller = User::factory()->create(['name' => 'PRIVATE VIDEO OWNER', 'status' => 'active']);
$listing = Listing::unguarded(fn () => Listing::create(['title' => 'آگهی آزمایش تبلیغ ویدئویی', 'description' => 'توضیح آزمایشی', 'price' => 1000000, 'currency' => 'IRR', 'user_id' => $seller->id, 'category_id' => Category::where('slug', 'electronics')->firstOrFail()->id, 'slug' => (string) Str::uuid(), 'status' => 'active']));
$file = new UploadedFile(base_path('tests/Fixtures/advertising-video.mp4'), 'clip.mp4', 'video/mp4', test: true);
$video = Video::createFromUploadedFile($listing, $file, ['title' => 'ویدئوی آماده آزمایش تبلیغ']);
$image = 'banners/video-ad-browser-'.Str::uuid().'.png';
$captions = 'banners/captions/video-ad-'.Str::uuid().'.vtt';
Storage::disk('public')->put($image, file_get_contents(public_path('img/category/phone.png')));
Storage::disk('public')->put($captions, "WEBVTT\n\n00:00.000 --> 00:08.000\nنمونه ویدئوی آزمایشی\n");
$banner = Banner::create(['title' => 'با انتخاب خودت ببین', 'alt' => 'پوستر تبلیغ ویدئویی', 'image' => $image, 'creative_format' => 'video', 'video_id' => $video->id, 'video_transcript' => 'این ویدئوی آزمایشی تصویر یک صفحه آبی را نشان می‌دهد و گفتار ندارد.', 'video_captions' => $captions, 'video_caption_language' => 'fa', 'device' => 'both', 'is_active' => true, 'review_status' => 'approved']);
BannerPlacement::where('key', 'home-top')->firstOrFail()->assignments()->create(['banner_id' => $banner->id, 'priority' => 1]);
file_put_contents($path, json_encode(['seller' => $seller->id, 'listing' => $listing->id, 'banner' => $banner->id, 'video' => $video->id, 'source' => $video->playableUrl(), 'image' => $image, 'captions' => $captions], JSON_THROW_ON_ERROR));
echo 'Video advertising fixtures created.'.PHP_EOL;
