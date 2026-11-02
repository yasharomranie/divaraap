<?php

declare(strict_types=1);

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Modules\Category\Models\Category;
use Modules\Conversation\App\Models\Conversation;
use Modules\Conversation\App\Models\ConversationMessage;
use Modules\Listing\Models\Listing;
use Modules\User\App\Models\Profile;
use Modules\User\App\Models\User;
use Spatie\Activitylog\Models\Activity;

require dirname(__DIR__, 2).'/vendor/autoload.php';
$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
if (app()->environment('production')) {
    throw new RuntimeException('Development only.');
}
$path = storage_path('framework/availability-review.json');
$action = $argv[1] ?? '';
if ($action !== '') {
    $state = json_decode(file_get_contents($path), true, flags: JSON_THROW_ON_ERROR);
    if ($action === 'prepare-race') {
        $state['race_revision'] = Profile::availabilityFor(User::findOrFail($state['seller']))['revision'];
        file_put_contents($path, json_encode($state, JSON_THROW_ON_ERROR));
        echo 'Race prepared.'.PHP_EOL;
        exit;
    }
    if (str_starts_with($action, 'race-')) {
        try {
            Profile::saveAvailabilityFor(User::findOrFail($state['seller']), ['enabled' => 1, 'until' => $state['until'], 'reason' => substr($action, 5), 'revision' => $state['race_revision'], 'public_consent' => 1]);
            echo json_encode(['result' => 'saved'], JSON_THROW_ON_ERROR).PHP_EOL;
        } catch (ValidationException $exception) {
            if (! isset($exception->errors()['revision'])) {
                throw $exception;
            }
            echo json_encode(['result' => 'stale'], JSON_THROW_ON_ERROR).PHP_EOL;
        }
        exit;
    }
    if ($action === 'state') {
        echo json_encode(['settings' => Profile::availabilityFor(User::findOrFail($state['seller'])), 'messages' => ConversationMessage::where('conversation_id', $state['thread'])->count()], JSON_THROW_ON_ERROR).PHP_EOL;
        exit;
    }
    if ($action === 'cleanup') {
        DB::table('conversation_messages')->where('conversation_id', $state['thread'])->delete();
        DB::table('conversations')->where('id', $state['thread'])->delete();
        DB::table('user_notifications')->whereIn('user_id', [$state['seller'], $state['buyer']])->delete();
        DB::table('buyer_publications')->where('listing_id', $state['listing'])->delete();
        Listing::withTrashed()->whereKey($state['listing'])->get()->each->forceDelete();
        User::withTrashed()->whereKey([$state['seller'], $state['buyer']])->get()->each->forceDelete();
        foreach ([Listing::class => [$state['listing']], User::class => [$state['seller'], $state['buyer']], Profile::class => [$state['profile']]] as $type => $ids) {
            Activity::where('subject_type', $type)->whereIn('subject_id', $ids)->delete();
        }
        unlink($path);
        echo 'Availability fixtures removed.'.PHP_EOL;
        exit;
    }
    throw new RuntimeException('Unknown fixture action.');
}
if (is_file($path)) {
    throw new RuntimeException('Clean previous fixtures first.');
}
$seller = User::create(['name' => 'فروشندهٔ آزمایش وضعیت', 'email' => Str::uuid().'@example.test', 'password' => 'Browser-test-123!', 'status' => 'active']);
$buyer = User::create(['name' => 'خریدار آزمایش وضعیت', 'email' => Str::uuid().'@example.test', 'password' => 'Browser-test-123!', 'status' => 'active']);
$seller->syncRoles([]);
$buyer->syncRoles([]);
Profile::saveShopFor($seller, ['shop_enabled' => true, 'shop_name' => 'فروشگاه آزمایش وضعیت'], Profile::shopVersion($seller));
$listing = Listing::unguarded(fn () => Listing::create(['title' => 'آگهی آزمایش عدم حضور', 'description' => 'آزمایش وضعیت پاسخ‌گویی', 'slug' => (string) Str::uuid(), 'status' => 'active', 'user_id' => $seller->id, 'category_id' => Category::where('name', 'Phones')->firstOrFail()->id, 'price' => 1000000, 'currency' => 'IRR']));
$thread = Conversation::openForListingBuyer($listing, $buyer->id);
file_put_contents($path, json_encode(['seller' => $seller->id, 'buyer' => $buyer->id, 'seller_email' => $seller->email, 'buyer_email' => $buyer->email, 'listing' => $listing->id, 'thread' => $thread->id, 'profile' => Profile::detailsForUser($seller)->id, 'until' => now()->addDays(29)->setTimezone('Asia/Tehran')->format('Y-m-d\TH:i')], JSON_THROW_ON_ERROR));
echo 'Availability fixtures created.'.PHP_EOL;
