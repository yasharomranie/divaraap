<?php

declare(strict_types=1);

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;
use Modules\Advertising\Models\AdvertisingEvent;
use Modules\Conversation\App\Models\Conversation;

require dirname(__DIR__, 2).'/vendor/autoload.php';
$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
if (app()->environment('production')) {
    throw new RuntimeException('Development only.');
}
$service = json_decode(file_get_contents(storage_path('framework/service-card-review.json')), true, flags: JSON_THROW_ON_ERROR);
$shop = json_decode(file_get_contents(storage_path('framework/shop-card-review.json')), true, flags: JSON_THROW_ON_ERROR);
if (in_array($argv[1] ?? '', ['shop-first', 'service-first'], true)) {
    foreach (['shop' => $shop['banner'], 'service' => $service['banner']] as $type => $id) {
        DB::table('advertising_assignments')->where('banner_id', $id)->update(['priority' => ($argv[1] === $type.'-first') ? -100 : -99]);
    }
    echo 'Priority changed.'.PHP_EOL;
    exit;
}
if (($argv[1] ?? '') === 'reset') {
    AdvertisingEvent::withTrashed()->whereIn('banner_id', [$shop['banner'], $service['banner']])->forceDelete();
    echo 'Events changed.'.PHP_EOL;
    exit;
}
if (($argv[1] ?? '') === 'cleanup-conversations') {
    $threads = Conversation::withTrashed()->where('listing_id', $service['listing'])->pluck('id');
    DB::table('conversation_messages')->whereIn('conversation_id', $threads)->delete();
    DB::table('conversations')->whereIn('id', $threads)->delete();
    echo 'Conversations removed.'.PHP_EOL;
    exit;
}
$counts = [];
foreach (['shop' => $shop['banner'], 'service' => $service['banner']] as $type => $id) {
    foreach (['click', 'shop_view', 'service', 'conversion'] as $kind) {
        $counts[$type][$kind] = AdvertisingEvent::where('banner_id', $id)->where('kind', $kind)->count();
    }
}
echo json_encode($counts, JSON_THROW_ON_ERROR).PHP_EOL;
