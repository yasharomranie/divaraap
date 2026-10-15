<?php

declare(strict_types=1);
use App\Support\Deployment\ReleaseLayout;

header('Cache-Control: no-store');
header('X-Frame-Options: DENY');
header("Content-Security-Policy: default-src 'none'; style-src 'self'; font-src 'self'; form-action 'self'; frame-ancestors 'none'; base-uri 'none'");
$root = dirname(__DIR__).'/divaraap';
if (! is_file($root.'/installed.lock') || ! is_file($root.'/recovery.token')) {
    http_response_code(404);
    exit;
}
session_name('divaraap_recovery');
session_start(['cookie_httponly' => true, 'cookie_secure' => ! empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off', 'cookie_samesite' => 'Strict']);
$_SESSION['csrf'] ??= bin2hex(random_bytes(32));
$message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require $root.'/app/Support/Deployment/ReleaseLayout.php';
    $layout = new ReleaseLayout($root, __DIR__);
    $saved = $layout->read('recovery.token');
    if (! is_string($_POST['csrf'] ?? null) || ! hash_equals($_SESSION['csrf'], $_POST['csrf'])
        || ! is_string($_POST['code'] ?? null) || ! hash_equals($saved['hash'] ?? '', hash('sha256', $_POST['code']))) {
        http_response_code(403);
        $message = 'کد بازیابی یا درخواست معتبر نیست.';
    } else {
        try {
            $lock = $layout->lock();
            $state = $layout->read('active-release.json');
            if (($state['can_rollback'] ?? false) && ($_POST['action'] ?? '') === 'rollback') {
                $layout->rollback();
            }
            if (is_file($root.'/deployment-maintenance.json')) {
                unlink($root.'/deployment-maintenance.json');
            }
            $job = $layout->read('deployment-job.json');
            $job['state'] = 'recovered';
            unset($job['token_hash']);
            $layout->write('deployment-job.json', $job);
            fclose($lock);
            $message = 'کد بازیابی شد. پایگاه داده تغییر نکرد؛ در صورت خطای مهاجرت، پشتیبان SQL را بررسی و بازیابی کنید.';
        } catch (Throwable) {
            $message = 'بازیابی انجام نشد؛ ممکن است به‌روزرسانی هنوز در حال اجرا باشد.';
        }
    }
}
?>
<!doctype html><html lang="fa" dir="rtl"><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>بازیابی سایت</title><link rel="stylesheet" href="/assets/fonts/vazirmatn/font.css"><link rel="stylesheet" href="/assets/deployment.css"><main><h1>بازیابی سایت</h1><p>برگشت فقط کد برنامه را تغییر می‌دهد. تغییرات پایگاه داده خودکار برگردانده نمی‌شوند. پشتیبان SQL در پوشهٔ خصوصی divaraap/deployments قرار دارد.</p><p role="status"><?= htmlspecialchars($message, ENT_QUOTES, 'UTF-8') ?></p><form method="post"><input type="hidden" name="csrf" value="<?= htmlspecialchars($_SESSION['csrf'], ENT_QUOTES, 'UTF-8') ?>"><label for="code">کد بازیابی</label><input id="code" name="code" type="password" required autocomplete="off" dir="ltr"><label for="action">روش بازیابی</label><select id="action" name="action"><option value="resume">بازکردن سایت با نسخهٔ فعال فعلی</option><option value="rollback">برگشت به کد نسخهٔ قبلی و بازکردن سایت</option></select><button type="submit">بازیابی</button></form><p><a href="/admin">ورود به مدیریت</a></p></main></html>
