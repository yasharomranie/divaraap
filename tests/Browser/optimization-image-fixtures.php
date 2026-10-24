<?php

declare(strict_types=1);

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Category\Models\Category;
use Modules\Listing\Models\Listing;
use Modules\User\App\Models\User;
use Spatie\Activitylog\Models\Activity;

require dirname(__DIR__, 2).'/vendor/autoload.php';
$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
if (app()->environment('production')) {
    throw new RuntimeException('Development only.');
}
$path = storage_path('framework/optimization-image-review.json');
if (($argv[1] ?? '') === 'cleanup') {
    $state = json_decode(file_get_contents($path), true, flags: JSON_THROW_ON_ERROR);
    DB::table('buyer_publications')->where('listing_id', $state['listing'])->delete();
    Listing::withTrashed()->whereKey($state['listing'])->get()->each->forceDelete();
    User::withTrashed()->whereKey($state['owner'])->get()->each->forceDelete();
    foreach ([Listing::class => $state['listing'], User::class => $state['owner']] as $class => $id) {
        Activity::where('subject_type', $class)->where('subject_id', $id)->delete();
    }
    unlink($path);
    echo 'Image fixtures removed.'.PHP_EOL;
    exit;
}
if (is_file($path)) {
    throw new RuntimeException('Clean previous image fixtures first.');
}
$user = User::create(['name' => 'Image performance fixture', 'email' => Str::uuid().'@example.test', 'password' => Str::random(32), 'status' => 'active']);
$listing = Listing::unguarded(fn () => Listing::create(['title' => 'Responsive image fixture', 'description' => 'Responsive image verification', 'slug' => (string) Str::uuid(), 'user_id' => $user->id, 'category_id' => Category::where('name', 'Phones')->firstOrFail()->id, 'status' => 'active', 'currency' => 'IRR', 'price' => 1000]));
$state = ['owner' => $user->id, 'listing' => $listing->id];
file_put_contents($path, json_encode($state, JSON_THROW_ON_ERROR));
$temporary = tempnam(sys_get_temp_dir(), 'image-optimization-');
try {
    $image = imagecreatetruecolor(1600, 1200);
    $source = imagecreatefrompng(public_path('img/category/car.png'));
    imagecopyresampled($image, $source, 0, 0, 0, 0, 1600, 1200, imagesx($source), imagesy($source));
    imagejpeg($image, $temporary, 95);
    $listing->attachListingImage($temporary, 'optimization-fixture.jpg', 'public');
    $listing->unsetRelation('media');
    $media = $listing->getFirstMedia('listing-images');
    $state += ['mobile' => $media->getUrl('card-mobile'), 'desktop' => $media->getUrl('card-desktop'), 'original_bytes' => filesize($media->getPath()), 'mobile_bytes' => filesize($media->getPath('card-mobile')), 'desktop_bytes' => filesize($media->getPath('card-desktop'))];
    file_put_contents($path, json_encode($state, JSON_THROW_ON_ERROR));
} finally {
    unlink($temporary);
}
echo 'Image fixtures created.'.PHP_EOL;
