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
if (app()->environment('production')) {
    throw new RuntimeException('Browser fixtures require development.');
}
$path = storage_path('framework/trade-checklist-review.json');
if (($argv[1] ?? '') === 'cleanup') {
    $state = json_decode(file_get_contents($path), true, 32, JSON_THROW_ON_ERROR);
    DB::transaction(function () use ($state): void {
        DB::table('buyer_publications')->where('listing_id', $state['listing'])->delete();
        Listing::withTrashed()->whereKey($state['listing'])->forceDelete();
        Category::withTrashed()->whereKey($state['category'])->forceDelete();
        Activity::where('subject_type', Listing::class)->where('subject_id', $state['listing'])->delete();
        Activity::where('subject_type', Category::class)->where('subject_id', $state['category'])->delete();
    });
    unlink($path);
    exit;
}
if (is_file($path)) {
    throw new RuntimeException('Clean up previous fixtures first.');
}
$category = Category::create(['name' => 'دسته آزمایش چک‌لیست', 'slug' => (string) Str::uuid(), 'is_active' => true, 'trade_checklist_mode' => 'custom', 'trade_checklist' => [['fa' => 'بررسی آزمایشی شماره سریال', 'en' => 'Inspect fixture serial']]]);
$seller = User::where('email', 'b@b.com')->firstOrFail();
$listing = Listing::unguarded(fn () => Listing::create(['title' => 'آگهی آزمایش چک‌لیست', 'description' => 'توضیح آزمایشی', 'price' => 1000000, 'currency' => 'IRR', 'user_id' => $seller->id, 'category_id' => $category->id, 'slug' => (string) Str::uuid(), 'status' => 'active']));
file_put_contents($path, json_encode(['category' => $category->id, 'listing' => $listing->id, 'slug' => $listing->slug], JSON_THROW_ON_ERROR));
echo 'Trade checklist fixtures created.'.PHP_EOL;
