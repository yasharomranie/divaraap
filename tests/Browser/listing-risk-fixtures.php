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
$path = storage_path('framework/listing-risk-review.json');
if (($argv[1] ?? '') === 'clear-signal') {
    $state = json_decode(file_get_contents($path), true, 32, JSON_THROW_ON_ERROR);
    Listing::findOrFail($state['sensitive'])->update(['description' => 'Ordinary item description']);
    exit;
}
if (($argv[1] ?? '') === 'cleanup') {
    $state = json_decode(file_get_contents($path), true, 32, JSON_THROW_ON_ERROR);
    DB::transaction(function () use ($state): void {
        DB::table('buyer_publications')->whereIn('listing_id', $state['listings'])->delete();
        Listing::withTrashed()->whereKey($state['listings'])->forceDelete();
        User::withTrashed()->whereKey($state['moderator'])->forceDelete();
        Activity::where('subject_type', Listing::class)->whereIn('subject_id', $state['listings'])->delete();
        Activity::where('subject_type', User::class)->where('subject_id', $state['moderator'])->delete();
    });
    unlink($path);
    exit;
}
if (is_file($path)) {
    throw new RuntimeException('Clean up previous fixtures first.');
}
$moderator = User::create(['name' => 'ناظر آزمایش ریسک', 'email' => Str::uuid().'@example.test', 'password' => 'risk-fixture-password', 'status' => 'active']);
$moderator->syncRoles(['moderator']);
$categoryId = Category::where('slug', 'electronics-phones')->firstOrFail()->id;
$ids = [];
foreach (['گوشی تکراری آزمایش ریسک', 'گوشی تکراری آزمایش ریسک', 'آگهی آزمایش درخواست حساس'] as $index => $title) {
    $listing = Listing::unguarded(fn () => Listing::create(['title' => $title, 'description' => $index === 2 ? 'لطفاً کد تایید را ارسال کنید.' : 'متن معمولی برای بررسی تکرار عنوان', 'price' => 1000, 'currency' => 'IRR', 'user_id' => $moderator->id, 'category_id' => $categoryId, 'slug' => (string) Str::uuid(), 'status' => 'pending']));
    $ids[] = $listing->id;
}
file_put_contents($path, json_encode(['moderator' => $moderator->id, 'email' => $moderator->email, 'listings' => $ids, 'sensitive' => $ids[2]], JSON_THROW_ON_ERROR));
echo 'Listing risk fixtures created.'.PHP_EOL;
