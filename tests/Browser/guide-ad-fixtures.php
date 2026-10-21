<?php

declare(strict_types=1);

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Modules\Advertising\Models\Banner;
use Modules\Advertising\Models\BannerPlacement;
use Modules\Page\Models\Page;
use Spatie\Activitylog\Models\Activity;

require dirname(__DIR__, 2).'/vendor/autoload.php';
$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
if (app()->environment('production')) {
    throw new RuntimeException('Browser fixtures require development.');
}
$path = storage_path('framework/guide-ad-review.json');
$action = $argv[1] ?? '';
if (in_array($action, ['cleanup', 'change-guide'], true)) {
    $state = json_decode(file_get_contents($path), true, 32, JSON_THROW_ON_ERROR);
    if ($action === 'change-guide') {
        Page::findOrFail($state['page'])->update(['body' => 'Changed guide body']);
        exit;
    }
    DB::transaction(function () use ($state): void {
        DB::table('advertising_assignments')->where('banner_id', $state['banner'])->delete();
        DB::table('advertising_events')->where('banner_id', $state['banner'])->delete();
        Banner::withTrashed()->whereKey($state['banner'])->forceDelete();
        Page::withTrashed()->whereKey($state['page'])->forceDelete();
        Activity::where('subject_type', Banner::class)->where('subject_id', $state['banner'])->delete();
    });
    Storage::disk('public')->delete($state['image']);
    unlink($path);
    exit;
}
if (is_file($path)) {
    throw new RuntimeException('Clean previous fixtures first.');
}
$guide = Page::create(['title' => 'راهنمای خرید امن', 'slug' => 'browser-guide-'.Str::uuid(), 'excerpt' => 'پیش از پرداخت، کالا را بررسی کنید و در محل امن قرار بگذارید.', 'body' => 'ابتدا کالا را بررسی کنید. اطلاعات حساس خود را در اختیار دیگران نگذارید.', 'placement' => 'help', 'is_published' => true]);
$image = 'banners/guide-browser-'.Str::uuid().'.png';
Storage::disk('public')->put($image, file_get_contents(public_path('img/category/phone.png')));
$banner = Banner::create(['title' => 'راهنمای کاربردی خرید', 'alt' => 'تصویر راهنمای خرید امن', 'image' => $image, 'creative_format' => 'guide', 'guide_page_id' => $guide->id, 'device' => 'both', 'is_active' => true, 'review_status' => 'approved']);
BannerPlacement::where('key', 'home-top')->firstOrFail()->assignments()->create(['banner_id' => $banner->id, 'priority' => 1]);
file_put_contents($path, json_encode(['page' => $guide->id, 'slug' => $guide->slug, 'banner' => $banner->id, 'image' => $image], JSON_THROW_ON_ERROR));
