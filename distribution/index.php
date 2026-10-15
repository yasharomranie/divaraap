<?php

declare(strict_types=1);

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
require $base.'/vendor/autoload.php';
$app = require $base.'/bootstrap/app.php';
$app->usePublicPath(__DIR__);
$app->handleRequest(Request::capture());
