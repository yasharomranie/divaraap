<?php

declare(strict_types=1);

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Category\Models\Category;
use Modules\Listing\Models\Listing;
use Modules\User\App\Models\Profile;
use Modules\User\App\Models\User;
use Spatie\Activitylog\Models\Activity;

require dirname(__DIR__, 2).'/vendor/autoload.php';
$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
if (app()->environment('production')) {
    throw new RuntimeException('Browser fixtures require development.');
}
$path = storage_path('framework/verification-review.json');
if (($argv[1] ?? '') === 'cleanup') {
    $state = json_decode(file_get_contents($path), true, 32, JSON_THROW_ON_ERROR);
    DB::transaction(function () use ($state): void {
        DB::table('buyer_publications')->where('listing_id', $state['listing'])->delete();
        Listing::withTrashed()->whereKey($state['listing'])->forceDelete();
        Activity::where('subject_type', Listing::class)->where('subject_id', $state['listing'])->delete();
        Activity::where('subject_type', User::class)->where('subject_id', $state['user'])->delete();
        $profiles = Profile::withTrashed()->where('user_id', $state['user'])->pluck('id');
        Activity::where('subject_type', Profile::class)->whereIn('subject_id', $profiles)->delete();
        User::withTrashed()->whereKey($state['user'])->forceDelete();
    });
    unlink($path);
    exit;
}
if (is_file($path)) {
    throw new RuntimeException('Clean up previous fixtures first.');
}
$user = User::create(['name' => 'فروشندهٔ آزمایش نشان', 'email' => Str::uuid().'@example.test', 'email_verified_at' => now(), 'password' => Str::random(32), 'status' => 'active']);
$user->forceFill(['email_verified_at' => now()])->save();
$listing = Listing::unguarded(fn () => Listing::create(['title' => 'آگهی آزمایش نشان', 'description' => 'توضیح آزمایشی', 'price' => 1000, 'currency' => 'IRR', 'user_id' => $user->id, 'category_id' => Category::where('name', 'Phones')->firstOrFail()->id, 'slug' => (string) Str::uuid(), 'status' => 'active', 'expires_at' => now()->addDays(5)]));
file_put_contents($path, json_encode(['user' => $user->id, 'listing' => $listing->id], JSON_THROW_ON_ERROR));
echo 'Seller verification fixtures created.'.PHP_EOL;
