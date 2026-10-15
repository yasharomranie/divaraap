<?php

declare(strict_types=1);
use App\Support\Deployment\ReleaseLayout;
use App\Support\Deployment\ReleaseRunner;
use Illuminate\Contracts\Console\Kernel;

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');
$root = dirname(__DIR__).'/divaraap';
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || ! is_file($root.'/installed.lock')) {
    http_response_code(404);
    exit;
}
require $root.'/app/Support/Deployment/ReleaseLayout.php';
$layout = new ReleaseLayout($root, __DIR__);
try {
    $lock = $layout->lock();
    $input = json_decode(file_get_contents('php://input', false, null, 0, 4096), true, 8, JSON_THROW_ON_ERROR);
    $job = $layout->read('deployment-job.json');
    if (! is_array($input) || ! is_string($input['token'] ?? null) || ! is_string($input['id'] ?? null)
        || ($job['state'] ?? null) !== 'prepared' || ($job['expires'] ?? 0) < time()
        || ! hash_equals($job['id'], $input['id']) || ! hash_equals($job['token_hash'], hash('sha256', $input['token']))) {
        http_response_code(403);
        echo json_encode(['ok' => false, 'message' => 'درخواست نامعتبر یا منقضی شده است.']);
        exit;
    }
    if (function_exists('set_time_limit')) {
        set_time_limit(240);
    }
    $base = $layout->code($job['id']);
    $job['state'] = 'running';
    unset($job['token_hash']);
    $layout->write('deployment-job.json', $job);
    $layout->write('deployment-maintenance.json', ['id' => $job['id'], 'started' => time()]);
    define('LARAVEL_START', microtime(true));
    require $base.'/vendor/autoload.php';
    $app = require $base.'/bootstrap/app.php';
    $app->make(Kernel::class)->bootstrap();
    echo json_encode($app->make(ReleaseRunner::class)->run($layout, $job), JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
} catch (Throwable $exception) {
    if (isset($job) && ($job['state'] ?? null) === 'running') {
        $job['state'] = 'failed';
        $job['step'] = 'bootstrap';
        $job['error_type'] = get_class($exception);
        $job['error'] = substr($exception->getMessage(), 0, 16000);
        try {
            $layout->write('deployment-job.json', $job);
        } catch (Throwable) {
        }
    }
    http_response_code(500);
    echo json_encode(['ok' => false, 'message' => 'راه‌اندازی نسخه ناموفق بود. صفحهٔ /recover.php را با کد بازیابی باز کنید.'], JSON_UNESCAPED_UNICODE);
}
