<?php

declare(strict_types=1);

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Modules\Advertising\Models\Banner;
use Modules\Advertising\Models\BannerPlacement;
use Modules\User\App\Models\Profile;
use Modules\User\App\Models\User;
use Spatie\Activitylog\Models\Activity;

require dirname(__DIR__, 2).'/vendor/autoload.php';
$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
if (app()->environment('production')) {
    throw new RuntimeException('Browser fixtures require development.');
}
$path = storage_path('framework/shop-card-review.json');
if (($argv[1] ?? '') === 'cleanup') {
    $state = json_decode(file_get_contents($path), true, 32, JSON_THROW_ON_ERROR);
    DB::transaction(function () use ($state): void {
        DB::table('advertising_assignments')->where('banner_id', $state['banner'])->delete();
        DB::table('advertising_events')->where('banner_id', $state['banner'])->delete();
        Banner::withTrashed()->whereKey($state['banner'])->forceDelete();
        User::withTrashed()->whereKey($state['seller'])->forceDelete();
        Activity::where('subject_type', Banner::class)->where('subject_id', $state['banner'])->delete();
        Activity::where('subject_type', User::class)->where('subject_id', $state['seller'])->delete();
        Activity::where('subject_type', Profile::class)->where('subject_id', $state['profile'])->delete();
    });
    Storage::disk('public')->delete($state['image']);
    unlink($path);
    exit;
}
if (($argv[1] ?? '') === 'change-shop') {
    $state = json_decode(file_get_contents($path), true, 32, JSON_THROW_ON_ERROR);
    Profile::detailsForUser(User::findOrFail($state['seller']))->forceFill(['shop_name' => 'فروشگاه تازه آزمایش کارت'])->save();
    exit;
}
if (is_file($path)) {
    throw new RuntimeException('Clean up previous fixtures first.');
}
$seller = User::create(['name' => 'PRIVATE SHOP OWNER', 'email' => Str::uuid().'@example.test', 'password' => 'fixture-password', 'status' => 'active']);
Profile::saveShopFor($seller, ['shop_enabled' => true, 'shop_name' => 'فروشگاه محلی آزمایش کارت', 'shop_description' => 'فروش و بررسی کالای دیجیتال در فروشگاه ما', 'shop_hours' => '۹ تا ۱۸'], Profile::shopVersion($seller));
$image = 'banners/shop-card-browser-'.Str::uuid().'.png';
Storage::disk('public')->put($image, file_get_contents(public_path('img/category/phone.png')));
$banner = Banner::create(['title' => 'تبلیغ فروشگاه آزمایشی', 'alt' => 'تصویر فروشگاه', 'image' => $image, 'creative_format' => 'shop', 'shop_user_id' => $seller->id, 'device' => 'both', 'is_active' => true, 'review_status' => 'approved']);
BannerPlacement::where('key', 'home-top')->firstOrFail()->assignments()->create(['banner_id' => $banner->id, 'priority' => 1]);
file_put_contents($path, json_encode(['seller' => $seller->id, 'profile' => Profile::detailsForUser($seller)->id, 'banner' => $banner->id, 'image' => $image], JSON_THROW_ON_ERROR));
echo 'Shop card fixtures created.'.PHP_EOL;
