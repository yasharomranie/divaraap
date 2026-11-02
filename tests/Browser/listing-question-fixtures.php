<?php

declare(strict_types=1);

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Category\Models\Category;
use Modules\Listing\Models\Listing;
use Modules\Listing\Models\ListingQuestion;
use Modules\User\App\Models\User;
use Spatie\Activitylog\Models\Activity;

require dirname(__DIR__, 2).'/vendor/autoload.php';
$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
if (app()->environment('production')) {
    throw new RuntimeException('Development only.');
}
$path = storage_path('framework/listing-questions-review.json');
$action = $argv[1] ?? '';
if ($action !== '') {
    $state = json_decode(file_get_contents($path), true, flags: JSON_THROW_ON_ERROR);
    if ($action === 'race') {
        $question = ListingQuestion::ask(User::findOrFail($state['buyer']), Listing::findOrFail($state['listing']), 'آیا این گوشی برای آزمون هم‌زمان موجود است؟', true);
        echo json_encode(['id' => $question->id], JSON_THROW_ON_ERROR).PHP_EOL;
        exit;
    }
    if ($action === 'state') {
        echo json_encode(ListingQuestion::withTrashed()->where('listing_id', $state['listing'])->get(['id', 'state', 'revision', 'deleted_at'])->toArray(), JSON_THROW_ON_ERROR).PHP_EOL;
        exit;
    }
    if ($action === 'cleanup') {
        $ids = ListingQuestion::withTrashed()->where('listing_id', $state['listing'])->pluck('id');
        ListingQuestion::withTrashed()->whereKey($ids)->forceDelete();
        DB::table('buyer_publications')->where('listing_id', $state['listing'])->delete();
        DB::table('user_notifications')->whereIn('user_id', [$state['seller'], $state['buyer']])->delete();
        Listing::withTrashed()->whereKey($state['listing'])->get()->each->forceDelete();
        User::withTrashed()->whereKey([$state['seller'], $state['buyer']])->get()->each->forceDelete();
        foreach ([ListingQuestion::class => $ids, Listing::class => [$state['listing']], User::class => [$state['seller'], $state['buyer']]] as $class => $subjectIds) {
            Activity::where('subject_type', $class)->whereIn('subject_id', $subjectIds)->delete();
        }
        unlink($path);
        echo 'Question fixtures removed.'.PHP_EOL;
        exit;
    }
    throw new RuntimeException('Unknown fixture action.');
}
if (is_file($path)) {
    throw new RuntimeException('Clean previous fixtures first.');
}
$seller = User::create(['name' => 'PRIVATE SELLER FIXTURE', 'email' => Str::uuid().'@example.test', 'password' => 'Browser-test-123!', 'status' => 'active']);
$buyer = User::create(['name' => 'PRIVATE BUYER FIXTURE', 'email' => Str::uuid().'@example.test', 'password' => 'Browser-test-123!', 'status' => 'active']);
$seller->syncRoles([]);
$buyer->syncRoles([]);
$listing = Listing::unguarded(fn () => Listing::create(['title' => 'گوشی آزمایشی پرسش و پاسخ', 'description' => 'بررسی پرسش عمومی آگهی', 'slug' => (string) Str::uuid(), 'status' => 'active', 'user_id' => $seller->id, 'category_id' => Category::where('name', 'Phones')->firstOrFail()->id, 'price' => 1000000, 'currency' => 'IRR']));
file_put_contents($path, json_encode(['seller' => $seller->id, 'buyer' => $buyer->id, 'seller_email' => $seller->email, 'buyer_email' => $buyer->email, 'listing' => $listing->id], JSON_THROW_ON_ERROR));
echo 'Question fixtures created.'.PHP_EOL;
