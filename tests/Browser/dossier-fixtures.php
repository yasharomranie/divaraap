<?php

declare(strict_types=1);

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Category\Models\Category;
use Modules\Favorite\App\Models\ComparisonDossier;
use Modules\Listing\Models\Listing;
use Modules\User\App\Models\User;
use Spatie\Activitylog\Models\Activity;

require dirname(__DIR__, 2).'/vendor/autoload.php';
$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
if (app()->environment('production')) {
    throw new RuntimeException('Development only.');
}
$path = storage_path('framework/dossier-review.json');
$action = $argv[1] ?? '';
if ($action !== '') {
    $state = json_decode(file_get_contents($path), true, flags: JSON_THROW_ON_ERROR);
    $owner = User::findOrFail($state['owner']);
    if ($action === 'cleanup') {
        $ids = ComparisonDossier::withTrashed()->where('user_id', $owner->id)->pluck('id');
        DB::table('comparison_dossiers')->whereIn('id', $ids)->delete();
        Listing::whereKey($state['listings'])->get()->each->forceDelete();
        Activity::where('subject_type', ComparisonDossier::class)->whereIn('subject_id', $ids)->delete();
        Activity::where('subject_type', Listing::class)->whereIn('subject_id', $state['listings'])->delete();
        User::whereKey([$owner->id, $state['seller']])->get()->each->forceDelete();
        Activity::where('subject_type', User::class)->whereIn('subject_id', [$owner->id, $state['seller']])->delete();
        unlink($path);
        echo 'Dossier fixtures cleaned.'.PHP_EOL;
        exit;
    }
    if ($action === 'expire') {
        DB::table('comparison_dossiers')->where('user_id', $owner->id)->update(['expires_at' => now()->subSecond()]);
        echo 'Dossier link expired.'.PHP_EOL;
        exit;
    }
    if ($action === 'change-listing') {
        Listing::findOrFail($state['listings'][0])->forceFill(['title' => 'گوشی به‌روز شده', 'price' => 40000000])->save();
        echo 'Public listing changed.'.PHP_EOL;
        exit;
    }
    if (str_starts_with($action, 'create-')) {
        $record = ComparisonDossier::createFor($owner, ['label' => 'Concurrent dossier', 'request_key' => $state['request_key'], 'consent' => '1'], Listing::whereKey(array_slice($state['listings'], 0, 2))->orderBy('id')->get());
        echo 'Dossier '.$record->id.PHP_EOL;
        exit;
    }
    throw new RuntimeException('Unknown fixture action.');
}
$users = [];
foreach (['owner', 'seller'] as $role) {
    $user = User::factory()->create(['name' => 'Dossier browser '.$role, 'email' => 'dossier-'.$role.'-'.Str::uuid().'@example.test', 'password' => 'Browser-test-123!', 'status' => 'active']);
    $user->syncRoles([]);
    $users[$role] = $user;
}
$category = Category::where('name', 'Phones')->firstOrFail()->id;
$listings = [];
foreach ([1, 2, 3] as $index) {
    $listings[] = Listing::unguarded(fn () => Listing::create(['title' => 'گوشی مقایسهٔ آزمایش '.$index, 'description' => 'توضیحات عمومی برای مقایسهٔ گوشی', 'slug' => (string) Str::uuid(), 'status' => 'active', 'user_id' => $users['seller']->id, 'category_id' => $category, 'currency' => 'IRR', 'price' => $index * 10000000, 'custom_fields' => ['condition' => 'used', 'storage' => (string) ($index * 128)]]))->id;
}
file_put_contents($path, json_encode(['owner' => $users['owner']->id, 'seller' => $users['seller']->id, 'email' => $users['owner']->email, 'listings' => $listings, 'request_key' => (string) Str::uuid()], JSON_THROW_ON_ERROR));
echo 'Dossier fixtures ready.'.PHP_EOL;
