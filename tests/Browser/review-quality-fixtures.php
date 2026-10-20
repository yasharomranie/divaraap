<?php

declare(strict_types=1);

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Category\Models\Category;
use Modules\Conversation\App\Models\Conversation;
use Modules\Conversation\App\Models\ConversationMessage;
use Modules\Listing\Models\Listing;
use Modules\Review\Models\Review;
use Modules\User\App\Models\User;
use Spatie\Activitylog\Models\Activity;

require dirname(__DIR__, 2).'/vendor/autoload.php';
$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
if (app()->environment('production')) {
    throw new RuntimeException('Browser fixtures require development.');
}
$path = storage_path('framework/review-quality.json');
if (($argv[1] ?? '') === 'cleanup') {
    $state = json_decode(file_get_contents($path), true, 32, JSON_THROW_ON_ERROR);
    DB::transaction(function () use ($state): void {
        $reviews = Review::withTrashed()->where('seller_id', $state['seller'])->pluck('id');
        Review::withTrashed()->whereKey($reviews)->forceDelete();
        Activity::where('subject_type', Review::class)->whereIn('subject_id', $reviews)->delete();
        DB::table('user_notifications')->where('user_id', $state['seller'])->delete();
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
$seller = User::create(['name' => 'فروشندهٔ آزمایش نظر', 'email' => Str::uuid().'@example.test', 'password' => Str::random(32), 'status' => 'active']);
$buyer = User::where('email', 'a@a.com')->firstOrFail();
$listing = Listing::unguarded(fn () => Listing::create(['title' => 'آگهی آزمایش نظر', 'description' => 'توضیح آزمایشی', 'price' => 1000, 'currency' => 'IRR', 'user_id' => $seller->id, 'category_id' => Category::where('name', 'Phones')->firstOrFail()->id, 'slug' => (string) Str::uuid(), 'status' => 'active']));
$thread = Conversation::unguarded(fn () => Conversation::create(['listing_id' => $listing->id, 'buyer_id' => $buyer->id, 'seller_id' => $seller->id, 'created_at' => now()->subDay()]));
foreach ([$buyer, $seller] as $sender) {
    ConversationMessage::unguarded(fn () => ConversationMessage::create(['conversation_id' => $thread->id, 'sender_id' => $sender->id, 'body' => 'PRIVATE REVIEW CONVERSATION', 'created_at' => now()->subHour()]));
}
file_put_contents($path, json_encode(['seller' => $seller->id, 'listing' => $listing->id], JSON_THROW_ON_ERROR));
echo 'Review quality fixtures created.'.PHP_EOL;
