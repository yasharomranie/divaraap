<?php

declare(strict_types=1);

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Modules\Admin\Models\OperationalMonitor;

Route::get('/operations/health', function (Request $request) {
    $token = (string) config('operational-monitor.probe_token');
    abort_unless(preg_match('/\A[a-f0-9]{64}\z/', $token) && hash_equals($token, (string) $request->bearerToken()), 404);
    $ok = OperationalMonitor::probeHealthy();

    return response()->json(['ok' => $ok], $ok ? 200 : 503)->header('Cache-Control', 'no-store');
})->name('operations.health');
