<?php

declare(strict_types=1);

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Category\Models\Category;
use Modules\Conversation\App\Models\Conversation;
use Modules\Conversation\App\Models\ConversationMessage;
use Modules\Listing\Models\Listing;
use Modules\User\App\Models\User;
use Spatie\Activitylog\Models\Activity;

require dirname(__DIR__, 2).'/vendor/autoload.php';
$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
if (app()->environment('production')) {
    throw new RuntimeException('Browser fixtures require development.');
}
$path = storage_path('framework/response-review.json');
if (($argv[1] ?? '') === 'cleanup') {
    $state = json_decode(file_get_contents($path), true, 32, JSON_THROW_ON_ERROR);
    DB::transaction(function () use ($state): void {
        DB::table('buyer_publications')->whereIn('listing_id', $state['listings'])->delete();
        Listing::withTrashed()->whereKey($state['listings'])->forceDelete();
        User::withTrashed()->whereKey($state['users'])->forceDelete();
        Activity::where('subject_type', Listing::class)->whereIn('subject_id', $state['listings'])->delete();
        Activity::where('subject_type', User::class)->whereIn('subject_id', $state['users'])->delete();
    });
    unlink($path);
    exit;
}
if (is_file($path)) {
    throw new RuntimeException('Clean up previous fixtures first.');
}
$users = [];
for ($i = 0; $i < 4; $i++) {
    $users[] = User::create(['name' => 'آزمایش زمان پاسخ '.$i, 'email' => Str::uuid().'@example.test', 'password' => Str::random(32), 'status' => 'active']);
}
$listings = [];
for ($i = 0; $i < 6; $i++) {
    $listing = Listing::unguarded(fn () => Listing::create(['title' => 'آگهی آزمایش پاسخ '.$i, 'description' => 'توضیح آزمایشی', 'price' => 1000, 'currency' => 'IRR', 'user_id' => $users[0]->id, 'category_id' => Category::where('name', 'Phones')->firstOrFail()->id, 'slug' => (string) Str::uuid(), 'status' => 'active', 'expires_at' => now()->addDays(5)]));
    $listings[] = $listing->id;
    $thread = Conversation::unguarded(fn () => Conversation::create(['listing_id' => $listing->id, 'seller_id' => $users[0]->id, 'buyer_id' => $users[1 + $i % 3]->id, 'created_at' => now()->subDays(2)]));
    ConversationMessage::unguarded(fn () => ConversationMessage::create(['conversation_id' => $thread->id, 'sender_id' => $thread->buyer_id, 'body' => 'PRIVATE RESPONSE FIXTURE', 'created_at' => now()->subDay()]));
    if ($i < 5) {
        ConversationMessage::unguarded(fn () => ConversationMessage::create(['conversation_id' => $thread->id, 'sender_id' => $thread->seller_id, 'body' => 'PRIVATE RESPONSE FIXTURE', 'created_at' => now()->subDay()->addMinutes(10)]));
    }
}
file_put_contents($path, json_encode(['user' => $users[0]->id, 'users' => array_map(fn ($user) => $user->id, $users), 'listings' => $listings], JSON_THROW_ON_ERROR));
echo 'Seller response fixtures created.'.PHP_EOL;
