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
use Modules\Location\Models\City;
use Modules\Location\Models\Country;
use Modules\User\App\Models\User;
use Spatie\Activitylog\Models\Activity;

require dirname(__DIR__, 2).'/vendor/autoload.php';
$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
if (app()->environment('production')) {
    throw new RuntimeException('Browser fixtures require development.');
}
$path = storage_path('framework/prepared-filter-review.json');
$action = $argv[1] ?? '';
if (in_array($action, ['cleanup', 'disable-city'], true)) {
    $state = json_decode(file_get_contents($path), true, 32, JSON_THROW_ON_ERROR);
    if ($action === 'disable-city') {
        City::findOrFail($state['city'])->update(['is_active' => false]);
        exit;
    }
    DB::transaction(function () use ($state): void {
        DB::table('advertising_assignments')->where('banner_id', $state['banner'])->delete();
        DB::table('advertising_events')->where('banner_id', $state['banner'])->delete();
        Banner::withTrashed()->whereKey($state['banner'])->forceDelete();
        Listing::withTrashed()->whereKey($state['listings'])->forceDelete();
        User::withTrashed()->whereKey($state['seller'])->forceDelete();
        Category::withTrashed()->whereKey($state['category'])->forceDelete();
        City::withTrashed()->whereKey($state['city'])->forceDelete();
        foreach ([Banner::class => [$state['banner']], Listing::class => $state['listings'], User::class => [$state['seller']], Category::class => [$state['category']], City::class => [$state['city']]] as $class => $ids) {
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
$seller = User::factory()->create(['status' => 'active']);
$category = Category::create(['name' => 'دسته آزمایش فیلتر آماده', 'slug' => (string) Str::uuid(), 'is_active' => true]);
$country = Country::where('is_active', true)->firstOrFail();
$city = City::create(['name' => 'شهر آزمایش فیلتر آماده', 'country_id' => $country->id, 'is_active' => true]);
$ids = [];
foreach ([1500 => 'آگهی ارزان آزمایش فیلتر', 3500 => 'آگهی ویژه آزمایش فیلتر'] as $price => $title) {
    $listing = Listing::unguarded(fn () => Listing::create(['title' => $title, 'description' => 'توضیح آزمایشی', 'price' => $price, 'currency' => 'IRR', 'city' => $city->name, 'country' => $country->name, 'user_id' => $seller->id, 'category_id' => $category->id, 'slug' => (string) Str::uuid(), 'status' => 'active']));
    $ids[] = $listing->id;
}
$image = 'banners/prepared-filter-browser-'.Str::uuid().'.png';
Storage::disk('public')->put($image, file_get_contents(public_path('img/category/phone.png')));
$banner = Banner::create(['title' => 'انتخاب هوشمند برای خرید بعدی', 'alt' => 'تصویر انتخاب‌ها', 'image' => $image, 'creative_format' => 'filter', 'filter_choices' => [
    ['label' => 'تا دو هزار ریال', 'category_id' => $category->id, 'city_id' => $city->id, 'max_price' => '۲۰۰۰', 'sort' => 'price_asc'],
    ['label' => 'بیش از سه هزار ریال', 'category_id' => $category->id, 'city_id' => $city->id, 'min_price' => '۳۰۰۰', 'sort' => 'newest'],
], 'device' => 'both', 'is_active' => true, 'review_status' => 'approved']);
BannerPlacement::where('key', 'home-top')->firstOrFail()->assignments()->create(['banner_id' => $banner->id, 'priority' => 1]);
file_put_contents($path, json_encode(['seller' => $seller->id, 'category' => $category->id, 'city' => $city->id, 'country' => $country->id, 'listings' => $ids, 'banner' => $banner->id, 'image' => $image], JSON_THROW_ON_ERROR));
echo 'Prepared filter fixtures created.'.PHP_EOL;
