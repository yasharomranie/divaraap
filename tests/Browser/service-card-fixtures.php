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
$path = storage_path('framework/service-card-review.json');
$action = $argv[1] ?? '';
if (in_array($action, ['cleanup', 'pause'], true)) {
    $state = json_decode(file_get_contents($path), true, 32, JSON_THROW_ON_ERROR);
    if ($action === 'pause') {
        Listing::findOrFail($state['listing'])->forceFill(['status' => 'paused'])->save();
        exit;
    }
    DB::transaction(function () use ($state): void {
        DB::table('advertising_assignments')->where('banner_id', $state['banner'])->delete();
        DB::table('advertising_events')->where('banner_id', $state['banner'])->delete();
        Banner::withTrashed()->whereKey($state['banner'])->forceDelete();
        DB::table('user_notifications')->whereIn('user_id', [$state['seller'], $state['buyer']])->delete();
        DB::table('buyer_publications')->where('listing_id', $state['listing'])->delete();
        Listing::withTrashed()->whereKey($state['listing'])->forceDelete();
        User::withTrashed()->whereKey([$state['seller'], $state['buyer']])->forceDelete();
        foreach ([Banner::class => [$state['banner']], Listing::class => [$state['listing']], User::class => [$state['seller'], $state['buyer']]] as $class => $ids) {
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
$seller = User::create(['name' => 'ارائه‌دهنده آزمایش خدمات', 'email' => Str::uuid().'@example.test', 'password' => 'service-fixture-password', 'status' => 'active']);
$buyer = User::create(['name' => 'خریدار آزمایش خدمات', 'email' => Str::uuid().'@example.test', 'password' => 'service-fixture-password', 'status' => 'active']);
$buyer->syncRoles([]);
$seller->syncRoles([]);
$listing = Listing::unguarded(fn () => Listing::create(['title' => 'تعمیر صندلی در محل آزمایش', 'description' => 'توضیح آزمایشی خدمات', 'price' => 1000000, 'currency' => 'IRR', 'user_id' => $seller->id, 'category_id' => Category::where('slug', 'services')->firstOrFail()->id, 'slug' => (string) Str::uuid(), 'status' => 'active']));
$image = 'banners/service-card-browser-'.Str::uuid().'.png';
Storage::disk('public')->put($image, file_get_contents(public_path('img/category/home_tools.png')));
$banner = Banner::create(['title' => 'خدمات منتخب محله شما', 'alt' => 'تصویر خدمات', 'image' => $image, 'creative_format' => 'service', 'service_listing_id' => $listing->id, 'device' => 'both', 'is_active' => true, 'review_status' => 'approved']);
BannerPlacement::where('key', 'home-top')->firstOrFail()->assignments()->create(['banner_id' => $banner->id, 'priority' => 1]);
file_put_contents($path, json_encode(['seller' => $seller->id, 'buyer' => $buyer->id, 'email' => $buyer->email, 'listing' => $listing->id, 'banner' => $banner->id, 'image' => $image], JSON_THROW_ON_ERROR));
echo 'Service fixtures created.'.PHP_EOL;
