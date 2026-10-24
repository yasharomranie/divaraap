<?php

declare(strict_types=1);

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Category\Models\Category;
use Modules\Favorite\App\Models\FavoriteCollection;
use Modules\Favorite\App\Models\FavoriteListing;
use Modules\Listing\Models\Listing;
use Modules\User\App\Models\User;
use Spatie\Activitylog\Models\Activity;
use Symfony\Component\HttpKernel\Exception\HttpException;

require dirname(__DIR__, 2).'/vendor/autoload.php';
$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
if (app()->environment('production')) {
    throw new RuntimeException('Development only.');
}
$path = storage_path('framework/shortlist-review.json');
$action = $argv[1] ?? '';
if ($action !== '') {
    $state = json_decode(file_get_contents($path), true, flags: JSON_THROW_ON_ERROR);
    $owner = User::findOrFail($state['owner']);
    $collection = FavoriteCollection::findOrFail($state['collection']);
    if ($action === 'cleanup') {
        Activity::where('subject_type', FavoriteCollection::class)->where('subject_id', $collection->id)->delete();
        DB::table('favorite_listings')->where('user_id', $owner->id)->delete();
        $collection->forceDelete();
        Listing::whereKey($state['listings'])->get()->each->forceDelete();
        User::whereKey([$owner->id, $state['seller']])->get()->each->forceDelete();
        foreach ([Listing::class => $state['listings'], User::class => [$owner->id, $state['seller']]] as $type => $ids) {
            Activity::where('subject_type', $type)->whereIn('subject_id', $ids)->delete();
        }
        unlink($path);
        echo 'Shortlist fixtures cleaned.'.PHP_EOL;
        exit;
    }
    if ($action === 'add-third') {
        FavoriteListing::rememberFor($owner, $state['listings'][2]);
        FavoriteListing::moveFor($owner, $state['listings'][2], $collection->id);
        echo 'Third choice added.'.PHP_EOL;
        exit;
    }
    if ($action === 'remove-first') {
        FavoriteListing::moveFor($owner, $state['listings'][0], null);
        echo 'First choice removed.'.PHP_EOL;
        exit;
    }
    if ($action === 'change-price') {
        Listing::findOrFail($state['listings'][1])->forceFill(['title' => 'انتخاب با قیمت تازه', 'price' => 33000000])->save();
        echo 'Public price changed.'.PHP_EOL;
        exit;
    }
    if ($action === 'expire') {
        $collection->forceFill(['share_expires_at' => now()->subSecond()])->save();
        echo 'Shortlist link expired.'.PHP_EOL;
        exit;
    }
    if ($action === 'prepare-race') {
        $state['race_revision'] = $collection->share_revision;
        file_put_contents($path, json_encode($state, JSON_THROW_ON_ERROR));
        echo 'Sharing race prepared.'.PHP_EOL;
        exit;
    }
    if (str_starts_with($action, 'create-')) {
        try {
            $collection->changeSharingFor($owner, ['action' => 'create', 'share_revision' => $state['race_revision'], 'consent' => '1', 'signature' => $collection->sharingPreview($owner)['signature']]);
            echo 'Shared collection '.$collection->id.PHP_EOL;
        } catch (HttpException $exception) {
            if ($exception->getStatusCode() !== 409) {
                throw $exception;
            }
            echo 'Rejected stale sharing.'.PHP_EOL;
        }
        exit;
    }
    throw new RuntimeException('Unknown fixture action.');
}
$users = [];
foreach (['owner', 'seller'] as $role) {
    $user = User::factory()->create(['email' => 'shortlist-'.$role.'-'.Str::uuid().'@example.test', 'password' => 'Browser-test-123!', 'status' => 'active']);
    $user->syncRoles([]);
    $users[$role] = $user;
}
$collection = FavoriteCollection::saveFor($users['owner'], 'نام خصوصی مجموعه آزمایش');
$listings = [];
foreach (['Phones', 'Cars', 'Phones'] as $index => $category) {
    $listing = Listing::unguarded(fn () => Listing::create(['title' => 'انتخاب عمومی آزمایش '.($index + 1), 'description' => 'اطلاعات عمومی برای آزمون اشتراک', 'slug' => (string) Str::uuid(), 'status' => 'active', 'category_id' => Category::where('name', $category)->firstOrFail()->id, 'user_id' => $users['seller']->id, 'price' => ($index + 1) * 10000000, 'currency' => 'IRR']));
    $listings[] = $listing->id;
    if ($index < 2) {
        FavoriteListing::rememberFor($users['owner'], $listing->id);
        FavoriteListing::moveFor($users['owner'], $listing->id, $collection->id);
    }
}
file_put_contents($path, json_encode(['owner' => $users['owner']->id, 'seller' => $users['seller']->id, 'email' => $users['owner']->email, 'collection' => $collection->id, 'listings' => $listings], JSON_THROW_ON_ERROR));
echo 'Shortlist fixtures ready.'.PHP_EOL;
