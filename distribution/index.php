<?php

declare(strict_types=1);

use App\Support\Deployment\ReleaseLayout;
use Illuminate\Http\Request;

define('LARAVEL_START', microtime(true));
$base = dirname(__DIR__).'/divaraap';
if (! is_file($base.'/installed.lock')) {
    header('Location: /install.php', true, 302);
    exit;
}
if (file_exists($maintenance = $base.'/storage/framework/maintenance.php')) {
    require $maintenance;
}
if (is_file($base.'/deployment-maintenance.json')) {
    http_response_code(503);
    header('Retry-After: 30');
    echo 'در حال به‌روزرسانی؛ کمی بعد دوباره تلاش کنید.';
    exit;
}
require $base.'/app/Support/Deployment/ReleaseLayout.php';
$layout = new ReleaseLayout($base, __DIR__);
$state = $layout->read('active-release.json');
$base = $layout->code($state['current'] ?? null);
require $base.'/vendor/autoload.php';
$app = require $base.'/bootstrap/app.php';
if (! isset($state['current'])) {
    $app->usePublicPath(__DIR__);
}
$app->handleRequest(Request::capture());
