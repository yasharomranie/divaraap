<?php

declare(strict_types=1);

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Location\Models\City;
use Modules\User\App\Models\NeighborhoodShop;
use Modules\User\App\Models\Profile;
use Modules\User\App\Models\User;
use Spatie\Activitylog\Models\Activity;

require dirname(__DIR__, 2).'/vendor/autoload.php';
$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
if (app()->environment('production')) {
    throw new RuntimeException('Development only.');
}
$path = storage_path('framework/neighborhood-review.json');
$action = $argv[1] ?? '';
if ($action !== '') {
    $state = json_decode(file_get_contents($path), true, flags: JSON_THROW_ON_ERROR);
    $seller = User::findOrFail($state['seller']);
    Auth::login(User::where('email', 'a@a.com')->firstOrFail());
    if ($action === 'cleanup') {
        $ids = NeighborhoodShop::withTrashed()->where('shop_user_id', $seller->id)->pluck('id');
        DB::table('neighborhood_shops')->whereIn('id', $ids)->delete();
        $profiles = Profile::where('user_id', $seller->id)->pluck('id');
        $seller->forceDelete();
        foreach ([NeighborhoodShop::class => $ids, Profile::class => $profiles, User::class => [$seller->id]] as $type => $subjects) {
            Activity::where('subject_type', $type)->whereIn('subject_id', $subjects)->delete();
        }
        unlink($path);
        echo 'Neighborhood fixtures cleaned.'.PHP_EOL;
        exit;
    }
    if ($action === 'change-shop') {
        Profile::saveShopFor($seller, ['shop_enabled' => true, 'shop_name' => 'فروشگاه بازبینی‌شده', 'shop_description' => 'معرفی عمومی تازه', 'shop_hours' => '10–18'], Profile::shopVersion($seller));
        echo 'Public shop changed.'.PHP_EOL;
        exit;
    }
    if (str_starts_with($action, 'create-')) {
        try {
            $record = NeighborhoodShop::saveSelection(auth()->user(), ['shop_user_id' => $seller->id, 'city_id' => $state['city'], 'neighborhood' => 'محله آزمایش', 'priority' => 100, 'is_active' => true, 'review_until' => now('Asia/Tehran')->addDays(30)->toDateString(), 'shop_version' => NeighborhoodShop::currentShopHash($seller->id)]);
            echo 'Selected '.$record->id.PHP_EOL;
        } catch (ValidationException) {
            echo 'Rejected duplicate.'.PHP_EOL;
        }
        exit;
    }
    throw new RuntimeException('Unknown fixture action.');
}
$seller = User::factory()->create(['name' => 'PRIVATE ACCOUNT', 'status' => 'active']);
$seller->syncRoles([]);
Profile::saveShopFor($seller, ['shop_enabled' => true, 'shop_name' => 'فروشگاه منتخب آزمایش', 'shop_description' => 'معرفی عمومی فروشگاه', 'shop_hours' => '9–17'], Profile::shopVersion($seller));
Profile::detailsForUser($seller)->forceFill(['city' => 'PRIVATE_CITY', 'phone' => 'PRIVATE_PHONE'])->save();
file_put_contents($path, json_encode(['seller' => $seller->id, 'city' => (int) array_key_first(City::wantedOptions())], JSON_THROW_ON_ERROR));
echo 'Neighborhood fixtures ready.'.PHP_EOL;
