<?php

declare(strict_types=1);

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Modules\Advertising\Models\Banner;
use Modules\Advertising\Models\BannerPlacement;
use Modules\Category\Models\Category;
use Modules\User\App\Models\Profile;
use Modules\User\App\Models\User;
use Spatie\Activitylog\Models\Activity;

require dirname(__DIR__, 2).'/vendor/autoload.php';
$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
if (app()->environment('production')) {
    throw new RuntimeException('Browser fixtures require development.');
}
$path = storage_path('framework/category-sponsor-review.json');
$action = $argv[1] ?? '';
if (in_array($action, ['cleanup', 'change-shop'], true)) {
    $state = json_decode(file_get_contents($path), true, 32, JSON_THROW_ON_ERROR);
    if ($action === 'change-shop') {
        Profile::detailsForUser(User::findOrFail($state['seller']))->forceFill(['shop_name' => 'فروشگاه تازه آزمایش حمایت'])->save();
        exit;
    }
    DB::transaction(function () use ($state): void {
        DB::table('advertising_assignments')->where('banner_id', $state['banner'])->delete();
        DB::table('advertising_events')->where('banner_id', $state['banner'])->delete();
        Banner::withTrashed()->whereKey($state['banner'])->forceDelete();
        Category::withTrashed()->whereKey($state['child'])->forceDelete();
        Category::withTrashed()->whereKey($state['category'])->forceDelete();
        User::withTrashed()->whereKey($state['seller'])->forceDelete();
        foreach ([Banner::class => [$state['banner']], Category::class => [$state['category'], $state['child']], User::class => [$state['seller']], Profile::class => [$state['profile']]] as $class => $ids) {
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
$seller = User::factory()->create(['name' => 'PRIVATE SPONSOR OWNER', 'status' => 'active']);
Profile::saveShopFor($seller, ['shop_enabled' => true, 'shop_name' => 'فروشگاه محلی آزمایش حمایت', 'shop_description' => 'معرفی عمومی فروشگاه', 'shop_hours' => '۹ تا ۱۸'], Profile::shopVersion($seller));
$category = Category::create(['name' => 'دسته آزمایش حامی', 'slug' => (string) Str::uuid(), 'is_active' => true]);
$child = Category::create(['name' => 'زیر‌دسته آزمایش حامی', 'slug' => (string) Str::uuid(), 'parent_id' => $category->id, 'is_active' => true]);
$image = 'banners/category-sponsor-browser-'.Str::uuid().'.png';
Storage::disk('public')->put($image, file_get_contents(public_path('img/category/phone.png')));
$banner = Banner::create(['title' => 'همراه انتخاب‌های محلی شما', 'alt' => 'نشان حامی', 'image' => $image, 'creative_format' => 'sponsor', 'sponsored_category_id' => $category->id, 'shop_user_id' => $seller->id, 'device' => 'both', 'is_active' => true, 'review_status' => 'approved']);
BannerPlacement::where('key', 'category-top')->firstOrFail()->assignments()->create(['banner_id' => $banner->id, 'priority' => 1]);
file_put_contents($path, json_encode(['seller' => $seller->id, 'profile' => Profile::detailsForUser($seller)->id, 'category' => $category->id, 'child' => $child->id, 'banner' => $banner->id, 'image' => $image], JSON_THROW_ON_ERROR));
echo 'Category sponsor fixtures created.'.PHP_EOL;
