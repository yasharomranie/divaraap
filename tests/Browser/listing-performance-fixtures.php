<?php

declare(strict_types=1);

use Carbon\CarbonImmutable;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Category\Models\Category;
use Modules\Listing\Models\Listing;
use Modules\Listing\Models\ListingPerformanceEvent;
use Modules\User\App\Models\User;
use Spatie\Activitylog\Models\Activity;

require dirname(__DIR__, 2).'/vendor/autoload.php';
$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
if (app()->environment('production')) {
    throw new RuntimeException('Browser fixtures require a development environment.');
}
$state = storage_path('framework/performance-review.json');
if (($argv[1] ?? '') === 'cleanup') {
    $id = (int) json_decode(file_get_contents($state), true, 32, JSON_THROW_ON_ERROR)['listing'];
    DB::transaction(function () use ($id): void {
        DB::table('listing_performance_events')->where('listing_id', $id)->delete();
        DB::table('buyer_publications')->where('listing_id', $id)->delete();
        Listing::withTrashed()->whereKey($id)->forceDelete();
        Activity::where('subject_type', Listing::class)->where('subject_id', $id)->delete();
    });
    unlink($state);
    exit;
}
if (is_file($state)) {
    throw new RuntimeException('Clean up existing browser fixtures before setup.');
}
$listing = Listing::unguarded(fn () => Listing::create([
    'title' => 'بررسی داشبورد عملکرد آگهی', 'description' => 'توضیح آگهی آزمایشی عملکرد',
    'slug' => (string) Str::uuid(), 'status' => 'active', 'price' => 1000000,
    'currency' => 'IRR', 'user_id' => User::where('email', 'a@a.com')->firstOrFail()->id,
    'category_id' => Category::where('name', 'Phones')->firstOrFail()->id, 'view_count' => 40,
]));
for ($i = 0; $i < 3; $i++) {
    ListingPerformanceEvent::create(['listing_id' => $listing->id, 'owner_id' => $listing->user_id, 'kind' => 'view', 'event_day' => CarbonImmutable::now(config('app.timezone'))->subDays($i)->toDateString(), 'visitor_hash' => hash('sha256', 'fixture-'.$i)]);
}
ListingPerformanceEvent::create(['listing_id' => $listing->id, 'owner_id' => $listing->user_id, 'kind' => 'contact', 'event_day' => CarbonImmutable::now(config('app.timezone'))->toDateString(), 'visitor_hash' => hash('sha256', 'fixture-contact')]);
file_put_contents($state, json_encode(['listing' => $listing->id], JSON_THROW_ON_ERROR));
echo $listing->id;
