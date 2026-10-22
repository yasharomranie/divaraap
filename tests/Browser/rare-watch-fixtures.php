<?php

declare(strict_types=1);

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Category\Models\Category;
use Modules\Favorite\App\Models\BuyerAlert;
use Modules\Favorite\App\Models\BuyerAlertReceipt;
use Modules\Favorite\App\Models\RareItemWatch;
use Modules\Listing\Models\Listing;
use Modules\Notification\Models\UserNotification;
use Modules\User\App\Models\User;
use Spatie\Activitylog\Models\Activity;

require dirname(__DIR__, 2).'/vendor/autoload.php';
$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
if (app()->environment('production')) {
    throw new RuntimeException('Development only.');
}
$path = storage_path('framework/rare-watch-review.json');
$action = $argv[1] ?? '';
if ($action !== '') {
    $state = json_decode(file_get_contents($path), true, flags: JSON_THROW_ON_ERROR);
    $buyer = User::findOrFail($state['buyer']);
    if ($action === 'cleanup') {
        $ids = RareItemWatch::withTrashed()->where('user_id', $buyer->id)->pluck('id')->all();
        DB::table('rare_item_watches')->whereIn('id', $ids)->delete();
        BuyerAlert::where('user_id', $buyer->id)->forceDelete();
        BuyerAlertReceipt::where('user_id', $buyer->id)->forceDelete();
        UserNotification::where('user_id', $buyer->id)->forceDelete();
        DB::table('buyer_publications')->whereIn('listing_id', $state['listings'])->delete();
        Listing::whereKey($state['listings'])->get()->each->forceDelete();
        foreach ([RareItemWatch::class => $ids, Listing::class => $state['listings'], User::class => [$buyer->id, $state['seller']]] as $type => $keys) {
            Activity::where('subject_type', $type)->whereIn('subject_id', $keys)->delete();
        }
        User::whereKey([$buyer->id, $state['seller']])->get()->each->forceDelete();
        unlink($path);
        echo 'Rare fixtures cleaned.'.PHP_EOL;
        exit;
    }
    if ($action === 'publish') {
        $listing = Listing::unguarded(fn () => Listing::create(['title' => 'نوکیا کلاسیک 123 سالم', 'description' => 'نمونه آزمایشی پیگیری کالای کمیاب', 'slug' => (string) Str::uuid(), 'status' => 'active', 'user_id' => $state['seller'], 'category_id' => $state['category'], 'price' => 1500000, 'currency' => 'IRR', 'expires_at' => now()->addDays(7)]));
        $state['listings'][] = $listing->id;
        file_put_contents($path, json_encode($state, JSON_THROW_ON_ERROR));
        echo 'Published '.$listing->id.PHP_EOL;
        exit;
    }
    if ($action === 'expire') {
        DB::table('rare_item_watches')->where('user_id', $buyer->id)->update(['expires_at' => now()->subMinute()]);
        echo 'Expired.'.PHP_EOL;
        exit;
    }
    if (str_starts_with($action, 'race-')) {
        $watch = RareItemWatch::createFor($buyer, ['search' => 'Race unique item', 'category' => $state['category']]);
        echo 'Watch '.$watch->id.PHP_EOL;
        exit;
    }
    if ($action === 'counts') {
        echo json_encode(['notifications' => UserNotification::where('user_id', $buyer->id)->count(), 'watches' => RareItemWatch::where('user_id', $buyer->id)->count()], JSON_THROW_ON_ERROR).PHP_EOL;
        exit;
    }
    throw new RuntimeException('Unknown fixture action.');
}
$buyer = User::factory()->create(['email' => 'rare-buyer-'.Str::uuid().'@example.test', 'password' => 'Browser-test-123!', 'status' => 'active']);
$seller = User::factory()->create(['status' => 'active']);
$buyer->syncRoles([]);
$seller->syncRoles([]);
$category = Category::where('name', 'Phones')->firstOrFail()->id;
$old = Listing::unguarded(fn () => Listing::create(['title' => 'نوکیا کلاسیک 123 قدیمی', 'description' => 'آگهی پیش از شروع پیگیری', 'slug' => (string) Str::uuid(), 'status' => 'active', 'user_id' => $seller->id, 'category_id' => $category, 'price' => 1500000, 'currency' => 'IRR', 'expires_at' => now()->addDays(7)]));
file_put_contents($path, json_encode(['buyer' => $buyer->id, 'email' => $buyer->email, 'seller' => $seller->id, 'category' => $category, 'listings' => [$old->id]], JSON_THROW_ON_ERROR));
echo 'Rare fixtures ready.'.PHP_EOL;
