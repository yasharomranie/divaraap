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
$path = storage_path('framework/bulk-review.json');
if (($argv[1] ?? '') === 'cleanup') {
    $state = json_decode(file_get_contents($path), true, 32, JSON_THROW_ON_ERROR);
    foreach ($state['ids'] as $id) {
        DB::transaction(function () use ($id): void {
            DB::table('buyer_publications')->where('listing_id', $id)->delete();
            Listing::withTrashed()->whereKey($id)->forceDelete();
            Activity::where('subject_type', Listing::class)->where('subject_id', $id)->delete();
        });
    }
    unlink($path);
    exit;
}
if (is_file($path)) {
    throw new RuntimeException('Clean up previous fixtures first.');
}
$ids = [];
foreach ([1, 2] as $number) {
    $listing = Listing::unguarded(fn () => Listing::create(['title' => 'آزمون گروهی مرورگر '.$number, 'description' => 'توضیح آزمایشی', 'price' => 1000, 'currency' => 'IRR', 'user_id' => User::where('email', 'a@a.com')->firstOrFail()->id, 'category_id' => Category::where('name', 'Phones')->firstOrFail()->id, 'slug' => (string) Str::uuid(), 'status' => 'active', 'expires_at' => now()->addDays(5)]));
    $ids[] = $listing->id;
}
file_put_contents($path, json_encode(['ids' => $ids], JSON_THROW_ON_ERROR));
echo 'Bulk review fixtures created.'.PHP_EOL;
