<?php

declare(strict_types=1);

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Modules\Advertising\Models\BannerPlacement;
use Modules\Advertising\Models\PlacementReservation;
use Modules\User\App\Models\User;
use Spatie\Activitylog\Models\Activity;

require dirname(__DIR__, 2).'/vendor/autoload.php';
$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
if (app()->environment('production')) {
    throw new RuntimeException('Browser fixtures require development.');
}
Auth::login(User::where('email', 'a@a.com')->firstOrFail());
$path = storage_path('framework/reservation-review.json');
$action = $argv[1] ?? '';
if ($action !== '') {
    $state = json_decode(file_get_contents($path), true, 32, JSON_THROW_ON_ERROR);
    if ($action === 'cleanup') {
        $ids = PlacementReservation::where('title', 'like', $state['prefix'].'%')->pluck('id')->all();
        DB::table('advertising_reservations')->whereIn('id', $ids)->delete();
        Activity::where('subject_type', PlacementReservation::class)->whereIn('subject_id', $ids)->delete();
        unlink($path);
        exit;
    }
    if ($action === 'verify') {
        if (PlacementReservation::where('title', 'like', $state['prefix'].' race%')->count() !== 1) {
            throw new RuntimeException('Overlapping concurrent reservations were accepted');
        }
        echo 'Verified one reservation under concurrent overlap.'.PHP_EOL;
        exit;
    }
    try {
        PlacementReservation::reserve(['request_key' => (string) Str::uuid(), 'placement_id' => $state['placement'], 'title' => $state['prefix'].' race '.$action, 'device' => 'both', 'starts_at' => $state['start'], 'ends_at' => $state['end']]);
        echo 'Reserved.'.PHP_EOL;
    } catch (ValidationException $exception) {
        echo 'Conflict rejected.'.PHP_EOL;
    }
    exit;
}
if (is_file($path)) {
    throw new RuntimeException('Clean previous fixtures first.');
}
$start = now()->addDays(2)->startOfDay();
file_put_contents($path, json_encode(['prefix' => 'CAL-'.Str::uuid(), 'placement' => BannerPlacement::where('key', 'home-top')->firstOrFail()->id, 'start' => $start->toIso8601String(), 'end' => $start->copy()->addHours(2)->toIso8601String(), 'month' => $start->copy()->timezone('Asia/Tehran')->format('Y-m'), 'local_start' => $start->copy()->addHours(3)->timezone('Asia/Tehran')->format('Y-m-d H:i'), 'local_end' => $start->copy()->addHours(4)->timezone('Asia/Tehran')->format('Y-m-d H:i')], JSON_THROW_ON_ERROR));
