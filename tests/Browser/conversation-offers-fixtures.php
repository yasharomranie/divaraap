<?php

declare(strict_types=1);

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Category\Models\Category;
use Modules\Conversation\App\Models\Conversation;
use Modules\Listing\Models\Listing;
use Modules\Offer\Models\Offer;
use Modules\User\App\Models\User;
use Spatie\Activitylog\Models\Activity;

require dirname(__DIR__, 2).'/vendor/autoload.php';
$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
if (app()->environment('production')) {
    throw new RuntimeException('Browser fixtures require development.');
}
$path = storage_path('framework/chat-offers-review.json');
if (($argv[1] ?? '') === 'cleanup') {
    $state = json_decode(file_get_contents($path), true, 32, JSON_THROW_ON_ERROR);
    DB::transaction(function () use ($state): void {
        Offer::withTrashed()->where('listing_id', $state['listing'])->forceDelete();
        DB::table('user_notifications')->where('user_id', $state['seller'])->orWhere('body', 'like', '%Chat offer browser fixture%')->delete();
        DB::table('buyer_publications')->where('listing_id', $state['listing'])->delete();
        Listing::withTrashed()->whereKey($state['listing'])->forceDelete();
        User::withTrashed()->whereKey($state['seller'])->forceDelete();
        Activity::where('subject_type', Listing::class)->where('subject_id', $state['listing'])->delete();
        Activity::where('subject_type', User::class)->where('subject_id', $state['seller'])->delete();
    });
    unlink($path);
    exit;
}
if (is_file($path)) {
    throw new RuntimeException('Clean up previous fixtures first.');
}
$seller = User::create(['name' => 'فروشنده آزمایش پیشنهاد', 'email' => Str::uuid().'@example.test', 'password' => 'chat-offer-fixture-password', 'status' => 'active']);
$seller->syncRoles([]);
$buyer = User::where('email', 'a@a.com')->firstOrFail();
$listing = Listing::unguarded(fn () => Listing::create(['title' => 'Chat offer browser fixture', 'description' => 'توضیح آزمایشی', 'price' => 1000000, 'currency' => 'IRR', 'user_id' => $seller->id, 'category_id' => Category::where('name', 'Phones')->firstOrFail()->id, 'slug' => (string) Str::uuid(), 'status' => 'active']));
$thread = Conversation::openForListingBuyer($listing, $buyer->id);
$thread->createMessageFor($buyer->id, 'CHAT OFFER EXISTING MESSAGE');
file_put_contents($path, json_encode(['seller' => $seller->id, 'email' => $seller->email, 'listing' => $listing->id, 'thread' => $thread->id], JSON_THROW_ON_ERROR));
echo 'Chat offer fixtures created.'.PHP_EOL;
