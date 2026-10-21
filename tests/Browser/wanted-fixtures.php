<?php

declare(strict_types=1);

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Modules\Category\Models\Category;
use Modules\Conversation\App\Models\Conversation;
use Modules\Listing\Models\Listing;
use Modules\Listing\Models\WantedRequest;
use Modules\Listing\Models\WantedResponse;
use Modules\User\App\Models\User;
use Spatie\Activitylog\Models\Activity;

require dirname(__DIR__, 2).'/vendor/autoload.php';
$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
if (app()->environment('production')) {
    throw new RuntimeException('Development only.');
}
$path = storage_path('framework/wanted-review.json');
$action = $argv[1] ?? '';
if ($action !== '') {
    $state = json_decode(file_get_contents($path), true, flags: JSON_THROW_ON_ERROR);
    $buyer = User::findOrFail($state['buyer']);
    $seller = User::findOrFail($state['seller']);
    Auth::login(User::where('email', 'a@a.com')->firstOrFail());
    if ($action === 'cleanup') {
        $requests = WantedRequest::where('user_id', $buyer->id)->pluck('id')->all();
        $responses = WantedResponse::whereIn('wanted_request_id', $requests)->pluck('id')->all();
        DB::table('wanted_responses')->whereIn('id', $responses)->delete();
        DB::table('wanted_requests')->whereIn('id', $requests)->delete();
        $threads = Conversation::whereIn('listing_id', $state['listings'])->pluck('id')->all();
        DB::table('conversation_messages')->whereIn('conversation_id', $threads)->delete();
        DB::table('conversations')->whereIn('id', $threads)->delete();
        Listing::whereKey($state['listings'])->get()->each->forceDelete();
        foreach ([WantedRequest::class => $requests, WantedResponse::class => $responses, Listing::class => $state['listings'], User::class => [$buyer->id, $seller->id]] as $type => $ids) {
            Activity::where('subject_type', $type)->whereIn('subject_id', $ids)->delete();
        }
        User::whereKey([$buyer->id, $seller->id])->get()->each->forceDelete();
        unlink($path);
        echo 'Wanted fixtures cleaned.'.PHP_EOL;
        exit;
    }
    $input = ['request_key' => $state['request_key'], 'title' => 'Concurrent wanted fixture', 'description' => 'A fixture for competing creation and suggestion requests.', 'category_id' => $state['category']];
    if (str_starts_with($action, 'create-')) {
        $request = WantedRequest::submit($buyer, $input);
        echo 'Created '.$request->id.PHP_EOL;
        exit;
    }
    if ($action === 'prepare-race') {
        $request = WantedRequest::submit($buyer, $input);
        $request->moderate(true);
        $state['race_request'] = $request->id;
        file_put_contents($path, json_encode($state, JSON_THROW_ON_ERROR));
        echo 'Suggestion race prepared.'.PHP_EOL;
        exit;
    }
    if (str_starts_with($action, 'suggest-')) {
        try {
            WantedResponse::submit($seller, $state['race_request'], $state['listings'][(int) substr($action, -1) - 1], 1);
            echo 'Suggested.'.PHP_EOL;
        } catch (ValidationException) {
            echo 'Rejected.'.PHP_EOL;
        }
        exit;
    }
    throw new RuntimeException('Unknown fixture action.');
}
$category = Category::where('name', 'Phones')->firstOrFail();
$users = [];
foreach (['buyer', 'seller'] as $role) {
    $user = User::factory()->create(['name' => 'Wanted browser '.$role, 'email' => 'wanted-'.$role.'-'.Str::uuid().'@example.test', 'password' => 'Browser-test-123!', 'status' => 'active']);
    $user->syncRoles([]);
    $users[$role] = $user;
}
$listings = [];
foreach ([1, 2] as $index) {
    $listings[] = Listing::unguarded(fn () => Listing::create(['title' => 'گوشی پیشنهادی آزمایش '.$index, 'description' => 'گوشی سالم برای آزمایش درخواست خرید', 'slug' => (string) Str::uuid(), 'status' => 'active', 'user_id' => $users['seller']->id, 'category_id' => $category->id, 'price' => 15000000, 'currency' => 'IRR', 'expires_at' => now()->addDays(7)]))->id;
}
file_put_contents($path, json_encode(['buyer' => $users['buyer']->id, 'seller' => $users['seller']->id, 'buyer_email' => $users['buyer']->email, 'seller_email' => $users['seller']->email, 'category' => $category->id, 'listings' => $listings, 'request_key' => (string) Str::uuid()], JSON_THROW_ON_ERROR));
echo 'Wanted fixtures ready.'.PHP_EOL;
