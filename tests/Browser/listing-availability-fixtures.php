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
$path = storage_path('framework/availability-review.json');
if (($argv[1] ?? '') === 'cleanup') {
    $state = json_decode(file_get_contents($path), true, 32, JSON_THROW_ON_ERROR);
    DB::transaction(function () use ($state): void {
        DB::table('buyer_publications')->where('listing_id', $state['listing'])->delete();
        Listing::withTrashed()->whereKey($state['listing'])->forceDelete();
        Activity::where('subject_type', Listing::class)->where('subject_id', $state['listing'])->delete();
    });
    unlink($path);
    exit;
}
if (is_file($path)) {
    throw new RuntimeException('Clean up previous fixtures first.');
}
$listing = Listing::unguarded(fn () => Listing::create(['title' => 'آزمون مرورگر توقف موقت نمایش', 'description' => 'توضیح آزمایشی آگهی', 'slug' => (string) Str::uuid(), 'status' => 'active', 'price' => 1000, 'currency' => 'IRR', 'user_id' => User::where('email', 'a@a.com')->firstOrFail()->id, 'category_id' => Category::where('name', 'Phones')->firstOrFail()->id, 'expires_at' => now()->addDays(5)]));
file_put_contents($path, json_encode(['listing' => $listing->id, 'title' => $listing->title], JSON_THROW_ON_ERROR));
echo 'Availability browser fixture created.'.PHP_EOL;
