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
$path = storage_path('framework/listing-collection-review.json');
$action = $argv[1] ?? '';
if (in_array($action, ['cleanup', 'pause', 'pause-two'], true)) {
    $state = json_decode(file_get_contents($path), true, 32, JSON_THROW_ON_ERROR);
    if ($action !== 'cleanup') {
        foreach (array_slice($state['listings'], 0, $action === 'pause' ? 1 : 2) as $id) {
            Listing::findOrFail($id)->forceFill(['status' => 'paused'])->save();
        }
        exit;
    }
    DB::transaction(function () use ($state): void {
        DB::table('advertising_assignments')->where('banner_id', $state['banner'])->delete();
        DB::table('advertising_events')->where('banner_id', $state['banner'])->delete();
        Banner::withTrashed()->whereKey($state['banner'])->forceDelete();
        Listing::withTrashed()->whereKey($state['listings'])->forceDelete();
        User::withTrashed()->whereKey($state['seller'])->forceDelete();
        foreach ([Banner::class => [$state['banner']], Listing::class => $state['listings'], User::class => [$state['seller']]] as $class => $ids) {
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
$seller = User::factory()->create(['name' => 'PRIVATE COLLECTION OWNER', 'status' => 'active']);
$ids = [];
foreach (['گوشی موبایل آزمایش مجموعه', 'صندلی آزمایش مجموعه', 'دوربین آزمایش مجموعه'] as $title) {
    $listing = Listing::unguarded(fn () => Listing::create(['title' => $title, 'description' => 'توضیح آزمایشی', 'price' => 1000000, 'currency' => 'IRR', 'user_id' => $seller->id, 'category_id' => Category::firstOrFail()->id, 'slug' => (string) Str::uuid(), 'status' => 'active']));
    $ids[] = $listing->id;
}
$image = 'banners/listing-collection-browser-'.Str::uuid().'.png';
Storage::disk('public')->put($image, file_get_contents(public_path('img/category/phone.png')));
$banner = Banner::create(['title' => 'مجموعه منتخب آزمایشی', 'alt' => 'تصویر مجموعه', 'image' => $image, 'creative_format' => 'collection', 'collection_listing_ids' => $ids, 'device' => 'both', 'is_active' => true, 'review_status' => 'approved']);
BannerPlacement::where('key', 'home-top')->firstOrFail()->assignments()->create(['banner_id' => $banner->id, 'priority' => 1]);
file_put_contents($path, json_encode(['seller' => $seller->id, 'listings' => $ids, 'banner' => $banner->id, 'image' => $image], JSON_THROW_ON_ERROR));
echo 'Collection fixtures created.'.PHP_EOL;
