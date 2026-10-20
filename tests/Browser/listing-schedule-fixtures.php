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
$path = storage_path('framework/schedule-review.json');
if (($argv[1] ?? '') === 'due') {
    $state = json_decode(file_get_contents($path), true, 32, JSON_THROW_ON_ERROR);
    $listing = Listing::findOrFail($state['listing']);
    if ($listing->statusValue() !== 'scheduled') {
        throw new RuntimeException('Only the scheduled fixture can become due.');
    }
    $listing->forceFill(['publish_at' => now()->subMinute()])->save();
    exit;
}
if (($argv[1] ?? '') === 'cleanup') {
    $state = json_decode(file_get_contents($path), true, 32, JSON_THROW_ON_ERROR);
    foreach ([$state['listing'], $state['pending']] as $id) {
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
$data = ['title' => 'آزمون مرورگر زمان‌بندی', 'description' => 'توضیح آزمایشی', 'price' => 1000, 'currency' => 'IRR', 'user_id' => User::where('email', 'a@a.com')->firstOrFail()->id, 'category_id' => Category::where('name', 'Phones')->firstOrFail()->id];
$listing = Listing::unguarded(fn () => Listing::create($data + ['slug' => (string) Str::uuid(), 'status' => 'active']));
$pending = Listing::unguarded(fn () => Listing::create($data + ['slug' => (string) Str::uuid(), 'status' => 'pending']));
file_put_contents($path, json_encode(['listing' => $listing->id, 'pending' => $pending->id, 'input' => now()->timezone('Asia/Tehran')->addHours(2)->format('Y-m-d\TH:i')], JSON_THROW_ON_ERROR));
echo 'Scheduled publication fixtures created.'.PHP_EOL;
