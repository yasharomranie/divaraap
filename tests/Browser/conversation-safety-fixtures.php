<?php

declare(strict_types=1);

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Category\Models\Category;
use Modules\Conversation\App\Models\Conversation;
use Modules\Conversation\App\Models\ConversationBlock;
use Modules\Listing\Models\Listing;
use Modules\Report\Models\Report;
use Modules\User\App\Models\User;
use Spatie\Activitylog\Models\Activity;

require dirname(__DIR__, 2).'/vendor/autoload.php';
$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
if (app()->environment('production')) {
    throw new RuntimeException('Browser fixtures require development.');
}
$path = storage_path('framework/chat-safety-review.json');
if (($argv[1] ?? '') === 'cleanup') {
    $state = json_decode(file_get_contents($path), true, 32, JSON_THROW_ON_ERROR);
    DB::transaction(function () use ($state): void {
        $reports = Report::withTrashed()->where('conversation_id', $state['thread'])->pluck('id');
        Report::withTrashed()->whereKey($reports)->forceDelete();
        Activity::where('subject_type', Report::class)->whereIn('subject_id', $reports)->delete();
        ConversationBlock::withTrashed()->where('blocker_id', $state['seller'])->orWhere('blocked_id', $state['seller'])->forceDelete();
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
$seller = User::create(['name' => 'آزمایش ایمنی گفتگو', 'email' => Str::uuid().'@example.test', 'password' => Str::random(32), 'status' => 'active']);
$buyer = User::where('email', 'a@a.com')->firstOrFail();
$listing = Listing::unguarded(fn () => Listing::create(['title' => 'آگهی آزمایش ایمنی', 'description' => 'توضیح آزمایشی', 'price' => 1000, 'currency' => 'IRR', 'user_id' => $seller->id, 'category_id' => Category::where('name', 'Phones')->firstOrFail()->id, 'slug' => (string) Str::uuid(), 'status' => 'active']));
$thread = Conversation::openForListingBuyer($listing, $buyer->id);
$thread->createMessageFor($buyer->id, 'EXISTING BUYER FIXTURE');
$message = $thread->createMessageFor($seller->id, 'SELECTED REPORT FIXTURE');
$thread->createMessageFor($seller->id, 'UNSELECTED REPORT FIXTURE');
file_put_contents($path, json_encode(['seller' => $seller->id, 'listing' => $listing->id, 'thread' => $thread->id, 'message' => $message->id], JSON_THROW_ON_ERROR));
echo 'Conversation safety fixtures created.'.PHP_EOL;
