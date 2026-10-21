<?php

declare(strict_types=1);

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Modules\Advertising\Models\AdvertisingExperiment;
use Modules\Advertising\Models\Banner;
use Modules\Advertising\Models\BannerPlacement;
use Modules\User\App\Models\User;
use Spatie\Activitylog\Models\Activity;

require dirname(__DIR__, 2).'/vendor/autoload.php';
$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
if (app()->environment('production')) {
    throw new RuntimeException('Development only.');
}
Auth::login(User::where('email', 'a@a.com')->firstOrFail());
$path = storage_path('framework/experiment-review.json');
if (($argv[1] ?? '') === 'cleanup') {
    $state = json_decode(file_get_contents($path), true, flags: JSON_THROW_ON_ERROR);
    $ids = AdvertisingExperiment::whereIn('banner_a_id', $state['banners'])->pluck('id')->all();
    DB::table('advertising_events')->whereIn('banner_id', $state['banners'])->delete();
    DB::table('advertising_experiments')->whereIn('id', $ids)->delete();
    DB::table('advertising_assignments')->whereIn('banner_id', $state['banners'])->delete();
    DB::table('advertising_banners')->whereIn('id', $state['banners'])->delete();
    $placement = BannerPlacement::findOrFail($state['placement']);
    $placement->forceFill($state['original'])->save();
    foreach ([Banner::class => $state['banners'], AdvertisingExperiment::class => $ids] as $type => $keys) {
        Activity::where('subject_type', $type)->whereIn('subject_id', $keys)->delete();
    }
    Storage::disk('public')->delete('banners/experiment-browser.png');
    unlink($path);
    echo 'Experiment fixtures cleaned.'.PHP_EOL;
    exit;
}
if (($argv[1] ?? '') === 'race-prepare') {
    $state = json_decode(file_get_contents($path), true, flags: JSON_THROW_ON_ERROR);
    $ids = [];
    foreach ([1, 2] as $index) {
        $ids[] = AdvertisingExperiment::create(['name' => 'Race experiment '.$index, 'placement_id' => $state['placement'], 'banner_a_id' => $state['banners'][0], 'banner_b_id' => $state['banners'][1], 'duration_days' => 1])->id;
    }
    $state['race'] = $ids;
    file_put_contents($path, json_encode($state, JSON_THROW_ON_ERROR));
    echo 'Race prepared.'.PHP_EOL;
    exit;
}
if (in_array($argv[1] ?? '', ['race-1', 'race-2'], true)) {
    $state = json_decode(file_get_contents($path), true, flags: JSON_THROW_ON_ERROR);
    try {
        AdvertisingExperiment::findOrFail($state['race'][(int) substr($argv[1], -1) - 1])->start();
        echo 'Started.'.PHP_EOL;
    } catch (ValidationException) {
        echo 'Rejected.'.PHP_EOL;
    }
    exit;
}
if (($argv[1] ?? '') === 'metrics') {
    $state = json_decode(file_get_contents($path), true, flags: JSON_THROW_ON_ERROR);
    $experiment = AdvertisingExperiment::whereIn('banner_a_id', $state['banners'])->latest('id')->firstOrFail();
    echo json_encode(['id' => $experiment->id, 'state' => $experiment->state, 'metrics' => $experiment->report()], JSON_THROW_ON_ERROR).PHP_EOL;
    exit;
}
$placement = BannerPlacement::where('key', 'home-top')->firstOrFail();
$original = $placement->only(['is_active', 'device']);
$placement->update(['is_active' => true, 'device' => 'both']);
Storage::disk('public')->put('banners/experiment-browser.png', file_get_contents(public_path('img/category/phone.png')));
$banners = [];
foreach (['A', 'B'] as $arm) {
    $banner = Banner::create(['title' => 'Browser experiment '.$arm, 'alt' => 'Browser experiment '.$arm, 'image' => 'banners/experiment-browser.png', 'url' => 'http://localhost:8000/listings', 'device' => 'both', 'is_active' => true, 'review_status' => 'approved']);
    $placement->assignments()->create(['banner_id' => $banner->id, 'priority' => 1]);
    $banners[] = $banner->id;
}
file_put_contents($path, json_encode(['banners' => $banners, 'placement' => $placement->id, 'original' => $original], JSON_THROW_ON_ERROR));
echo 'Experiment fixtures ready.'.PHP_EOL;
