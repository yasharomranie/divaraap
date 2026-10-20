<?php

declare(strict_types=1);

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;
use Modules\Listing\Models\Listing;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

if (config('demo.enabled')) {
    Schedule::command('demo:cleanup')->hourly();
}

Schedule::call(fn () => Listing::publishDue(200))->everyMinute()->name('publish-scheduled-listings')->withoutOverlapping();
