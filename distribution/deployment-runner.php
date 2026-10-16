<?php

declare(strict_types=1);
use App\Support\Deployment\ReleaseLayout;
use App\Support\Deployment\ReleaseRunner;
use Illuminate\Contracts\Console\Kernel;

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');
$secure = ! empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
$local = PHP_SAPI === 'cli-server' && in_array(parse_url('http://'.($_SERVER['HTTP_HOST'] ?? ''), PHP_URL_HOST), ['localhost', '127.0.0.1'], true);
if (! $secure && ! $local) {
    http_response_code(403);
    exit('HTTPS is required.');
}
$root = dirname(__DIR__).'/divaraap';
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || ! is_file($root.'/installed.lock')) {
    http_response_code(404);
    exit;
}
require $root.'/app/Support/Deployment/ReleaseLayout.php';
$layout = new ReleaseLayout($root, __DIR__);
$started = false;
try {
    $input = json_decode(file_get_contents('php://input', false, null, 0, 4096), true, 8, JSON_THROW_ON_ERROR);
    $job = $layout->read('deployment-job.json');
    if (($input['action'] ?? null) === 'status') {
        $hash = $job['status_token_hash'] ?? $job['token_hash'] ?? null;
        if (! is_string($input['id'] ?? null) || ! is_string($input['token'] ?? null) || ! is_string($hash)
            || ! hash_equals($job['id'] ?? '', $input['id']) || ($job['expires'] ?? 0) + 3600 < time()
            || ! hash_equals($hash, hash('sha256', $input['token']))) {
            http_response_code(403);
            echo json_encode(['ok' => false, 'message' => 'دسترسی بررسی وضعیت معتبر نیست.']);
            exit;
        }
        $messages = [
            'prepared' => 'بسته آماده است؛ اجراکننده هنوز نصب را شروع نکرده است.',
            'running' => 'نصب روی سرور در حال اجراست.',
            'completed' => 'نسخهٔ جدید فعال شد.',
            'failed' => 'نصب متوقف شده است؛ از صفحهٔ بازیابی استفاده کنید.',
            'recovered' => 'بازیابی ثبت شده است.',
        ];
        $step = $job['step'] ?? '';
        $steps = ['database_backup' => 'پشتیبان‌گیری از پایگاه داده', 'migrate' => 'اجرای مهاجرت‌ها', 'config:clear' => 'پاک‌سازی تنظیمات', 'route:clear' => 'پاک‌سازی مسیرها', 'view:clear' => 'پاک‌سازی قالب‌ها', 'config:cache' => 'آماده‌سازی تنظیمات', 'route:cache' => 'آماده‌سازی مسیرها', 'view:cache' => 'آماده‌سازی قالب‌ها', 'activate' => 'فعال‌سازی نسخه', 'bootstrap' => 'راه‌اندازی نسخه'];
        $state = $job['state'] ?? '';
        echo json_encode(['ok' => true, 'state' => $state, 'message' => ($messages[$state] ?? 'وضعیت نصب مشخص نیست.').(isset($steps[$step]) ? "\nمرحله: ".$steps[$step] : '')], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        exit;
    }
    $lock = $layout->lock();
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
    $started = true;
    $layout->write('deployment-maintenance.json', ['id' => $job['id'], 'started' => time()]);
    define('LARAVEL_START', microtime(true));
    require $base.'/vendor/autoload.php';
    $app = require $base.'/bootstrap/app.php';
    $app->make(Kernel::class)->bootstrap();
    echo json_encode($app->make(ReleaseRunner::class)->run($layout, $job), JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
} catch (Throwable $exception) {
    if ($started && isset($job) && ($job['state'] ?? null) === 'running') {
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
