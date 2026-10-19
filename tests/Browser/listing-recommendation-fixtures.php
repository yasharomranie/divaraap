<?php

declare(strict_types=1);

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Category\Models\Category;
use Modules\Listing\Models\Listing;
use Modules\User\App\Models\User;
use Spatie\Activitylog\Models\Activity;

require dirname(__DIR__, 2).'/vendor/autoload.php';
$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$state = storage_path('framework/recommendation-review.json');
if (app()->environment('production')) {
    throw new RuntimeException('Browser fixtures require a development environment.');
}
if (($argv[1] ?? '') === 'cleanup') {
    $data = json_decode(file_get_contents($state), true, 32, JSON_THROW_ON_ERROR);
    DB::transaction(function () use ($data): void {
        DB::table('buyer_publications')->whereIn('listing_id', $data['listings'])->delete();
        Listing::withTrashed()->whereIn('id', $data['listings'])->forceDelete();
        Category::withTrashed()->whereKey($data['category'])->forceDelete();
        Activity::where('subject_type', Listing::class)->whereIn('subject_id', $data['listings'])->delete();
        Activity::where('subject_type', Category::class)->where('subject_id', $data['category'])->delete();
    });
    unlink($state);
    exit;
}
if (is_file($state)) {
    throw new RuntimeException('Clean up existing browser fixtures before setup.');
}
$category = Category::create(['name' => 'گوشی‌های بررسی پیشنهاد', 'slug' => 'recommendation-review-'.Str::random(8), 'is_active' => true]);
$ids = [];
foreach ([['گوشی مرجع بررسی پیشنهادها', 10000000, 'Tehran'], ['گوشی پیشنهادی نزدیک با قیمت مناسب', 11000000, 'Tehran'], ['گوشی پیشنهادی از شهر دیگر', 10000000, 'Shiraz']] as [$title, $price, $city]) {
    $listing = Listing::unguarded(fn () => Listing::create(['title' => $title, 'description' => 'توضیحات آگهی برای بررسی پیشنهادهای مرتبط', 'slug' => (string) Str::uuid(), 'status' => 'active', 'price' => $price, 'currency' => 'IRR', 'country' => 'Iran', 'city' => $city, 'category_id' => $category->id, 'user_id' => User::where('email', 'a@a.com')->firstOrFail()->id, 'images' => ['/img/category/phone.png']]));
    $ids[] = $listing->id;
}
file_put_contents($state, json_encode(['category' => $category->id, 'listings' => $ids]));
echo json_encode($ids);
