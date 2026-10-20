<?php

declare(strict_types=1);

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Category\Models\Category;
use Modules\Listing\Models\Listing;
use Modules\Listing\Models\ListingDraft;
use Modules\Location\Models\Country;
use Modules\User\App\Models\User;
use Spatie\Activitylog\Models\Activity;

require dirname(__DIR__, 2).'/vendor/autoload.php';
$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
if (app()->environment('production')) {
    throw new RuntimeException('Browser fixtures require development.');
}
$path = storage_path('framework/duplicate-review.json');
if (($argv[1] ?? '') === 'cleanup') {
    $state = json_decode(file_get_contents($path), true, 32, JSON_THROW_ON_ERROR);
    $source = Listing::withTrashed()->findOrFail($state['listing']);
    $source->clearMediaCollection('listing-images');
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
$user = User::where('email', 'a@a.com')->firstOrFail();
if (ListingDraft::currentFor($user)) {
    throw new RuntimeException('Use a development account with no active draft.');
}
$listing = Listing::unguarded(fn () => Listing::create(['title' => 'آزمون مرورگر آگهی مشابه', 'description' => 'توضیح واقعی کالای قبلی برای بررسی انتقال', 'slug' => (string) Str::uuid(), 'status' => 'sold', 'price' => 1000, 'currency' => config('app.default_currency', 'IRR'), 'user_id' => $user->id, 'category_id' => Category::where('name', 'Phones')->firstOrFail()->id, 'country' => Country::where('is_active', true)->firstOrFail()->name, 'latitude' => 35.7, 'longitude' => 51.4]));
$image = UploadedFile::fake()->image('duplicate-review.jpg', 200, 160);
$listing->attachListingImage($image->getRealPath(), 'duplicate-review.jpg');
file_put_contents($path, json_encode(['listing' => $listing->id, 'title' => $listing->title], JSON_THROW_ON_ERROR));
echo 'Duplicate browser fixture created.'.PHP_EOL;
