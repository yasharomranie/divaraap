<?php

declare(strict_types=1);

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Category\Models\Category;
use Modules\Conversation\App\Models\Conversation;
use Modules\Conversation\App\Models\SavedReply;
use Modules\Listing\Models\Listing;
use Modules\User\App\Models\User;
use Spatie\Activitylog\Models\Activity;

require dirname(__DIR__, 2).'/vendor/autoload.php';
$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
if (app()->environment('production')) {
    throw new RuntimeException('Browser fixtures require development.');
}
$path = storage_path('framework/saved-replies-review.json');
if (($argv[1] ?? '') === 'cleanup') {
    $state = json_decode(file_get_contents($path), true, 32, JSON_THROW_ON_ERROR);
    DB::transaction(function () use ($state): void {
        Conversation::withTrashed()->whereKey($state['conversation'])->forceDelete();
        Listing::withTrashed()->whereKey($state['listing'])->forceDelete();
        SavedReply::withTrashed()->whereKey($state['reply'])->forceDelete();
        Activity::where('subject_type', Listing::class)->where('subject_id', $state['listing'])->delete();
    });
    unlink($path);
    exit;
}
if (is_file($path)) {
    throw new RuntimeException('Clean up previous fixtures first.');
}
$owner = User::where('email', 'a@a.com')->firstOrFail();
$buyer = User::whereKeyNot($owner->id)->firstOrFail();
$listing = Listing::unguarded(fn () => Listing::create(['title' => 'Saved reply browser fixture', 'description' => '', 'slug' => (string) Str::uuid(), 'status' => 'pending', 'price' => 1000, 'currency' => 'IRR', 'user_id' => $owner->id, 'category_id' => Category::where('name', 'Phones')->firstOrFail()->id]));
$conversation = Conversation::openForListingBuyer($listing, $buyer->id);
$reply = SavedReply::storeFor($owner, ['title' => 'پاسخ آزمایشی مرورگر', 'body' => "سلام\nبرای هماهنگی بازدید پیام بدهید.", 'position' => 0]);
file_put_contents($path, json_encode(['listing' => $listing->id, 'conversation' => $conversation->id, 'reply' => $reply->id], JSON_THROW_ON_ERROR));
echo 'Saved reply browser fixture created.'.PHP_EOL;
