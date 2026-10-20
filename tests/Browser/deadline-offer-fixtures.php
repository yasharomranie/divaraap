<?php

declare(strict_types=1);

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Modules\Advertising\Models\Banner;
use Modules\Advertising\Models\BannerPlacement;
use Modules\Category\Models\Category;
use Modules\Listing\Models\Listing;
use Modules\User\App\Models\User;
use Spatie\Activitylog\Models\Activity;

require dirname(__DIR__, 2).'/vendor/autoload.php';
$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
if (app()->environment('production')) {
    throw new RuntimeException('Browser fixtures require development.');
}
$path = storage_path('framework/deadline-offer-review.json');
$action = $argv[1] ?? '';
if (in_array($action, ['cleanup', 'expire'], true)) {
    $state = json_decode(file_get_contents($path), true, 32, JSON_THROW_ON_ERROR);
    if ($action === 'expire') {
        Banner::findOrFail($state['banner'])->forceFill(['ends_at' => now()->subSecond()])->saveQuietly();
        exit;
    }
    DB::transaction(function () use ($state): void {
        DB::table('advertising_assignments')->where('banner_id', $state['banner'])->delete();
        DB::table('advertising_events')->where('banner_id', $state['banner'])->delete();
        Banner::withTrashed()->whereKey($state['banner'])->forceDelete();
        Listing::withTrashed()->whereKey($state['listing'])->forceDelete();
        User::withTrashed()->whereKey($state['seller'])->forceDelete();
        foreach ([Banner::class => [$state['banner']], Listing::class => [$state['listing']], User::class => [$state['seller']]] as $class => $ids) {
            Activity::where('subject_type', $class)->whereIn('subject_id', $ids)->delete();
        }
    });
    Storage::disk('public')->delete($state['image']);
    unlink($path);
    exit;
}
if (is_file($path)) {
    throw new RuntimeException('Clean previous fixtures first.');
}
$seller = User::factory()->create(['name' => 'PRIVATE DEADLINE OWNER', 'status' => 'active']);
$listing = Listing::unguarded(fn () => Listing::create(['title' => 'آگهی آزمایش پیشنهاد زمان‌دار', 'description' => 'توضیح آزمایشی', 'price' => 1000000, 'currency' => 'IRR', 'user_id' => $seller->id, 'category_id' => Category::where('slug', 'electronics')->firstOrFail()->id, 'slug' => (string) Str::uuid(), 'status' => 'active']));
$image = 'banners/deadline-offer-browser-'.Str::uuid().'.png';
Storage::disk('public')->put($image, file_get_contents(public_path('img/category/phone.png')));
$banner = Banner::create(['title' => 'پیشنهاد با پایان مشخص', 'alt' => 'تصویر پیشنهاد', 'image' => $image, 'creative_format' => 'deadline', 'offer_listing_id' => $listing->id, 'ends_at' => now()->addMinutes(6)->startOfMinute(), 'device' => 'both', 'is_active' => true, 'review_status' => 'approved']);
BannerPlacement::where('key', 'home-top')->firstOrFail()->assignments()->create(['banner_id' => $banner->id, 'priority' => 1]);
file_put_contents($path, json_encode(['seller' => $seller->id, 'listing' => $listing->id, 'banner' => $banner->id, 'image' => $image, 'deadline' => $banner->ends_at->toIso8601String()], JSON_THROW_ON_ERROR));
echo 'Deadline fixtures created.'.PHP_EOL;
