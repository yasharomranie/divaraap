<?php

declare(strict_types=1);
use App\Support\Deployment\ReleaseLayout;

header('Cache-Control: no-store');
header('X-Frame-Options: DENY');
header('X-Content-Type-Options: nosniff');
header("Content-Security-Policy: default-src 'none'; style-src 'self'; font-src 'self'; form-action 'self'; frame-ancestors 'none'; base-uri 'none'");
$secure = ! empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
$local = PHP_SAPI === 'cli-server' && in_array(parse_url('http://'.($_SERVER['HTTP_HOST'] ?? ''), PHP_URL_HOST), ['localhost', '127.0.0.1'], true);
if (! $secure && ! $local) {
    http_response_code(403);
    exit('HTTPS is required.');
}
$root = dirname(__DIR__).'/divaraap';
if (! is_file($root.'/installed.lock') || ! is_file($root.'/recovery.token')) {
    http_response_code(404);
    exit;
}
session_name('divaraap_deployment_repair');
session_start(['cookie_httponly' => true, 'cookie_secure' => $secure, 'cookie_samesite' => 'Strict']);
$_SESSION['csrf'] ??= bin2hex(random_bytes(32));
$message = '';
$success = false;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $saved = json_decode(file_get_contents($root.'/recovery.token'), true);
    if (! is_string($_POST['csrf'] ?? null) || ! hash_equals($_SESSION['csrf'], $_POST['csrf'])
        || ! is_string($_POST['code'] ?? null) || ! is_string($saved['hash'] ?? null)
        || ! hash_equals($saved['hash'], hash('sha256', $_POST['code']))) {
        http_response_code(403);
        $message = 'کد بازیابی یا درخواست معتبر نیست.';
    } else {
        $lock = null;
        $temporary = [];
        $changed = [];
        $originals = [];
        $backups = [];
        try {
            require $root.'/app/Support/Deployment/ReleaseLayout.php';
            $layout = new ReleaseLayout($root, __DIR__);
            $lock = $layout->lock();
            $job = $layout->read('deployment-job.json');
            if (is_file($root.'/deployment-maintenance.json') || ($job['state'] ?? null) === 'running') {
                throw new RuntimeException('نصب یا بازیابی هنوز در جریان است؛ در این وضعیت تعمیر انجام نمی‌شود.');
            }
            $state = $layout->read('active-release.json');
            $ids = array_values(array_unique(array_filter([$state['current'] ?? null, ($job['state'] ?? null) === 'prepared' ? ($job['id'] ?? null) : null])));
            $codes = [$root];
            $publics = [__DIR__];
            foreach ($ids as $id) {
                $codes[] = $layout->code($id);
                $publics[] = __DIR__.'/_releases/'.$id;
            }
            $files = json_decode(base64_decode('__DEPLOYMENT_REPAIR_PAYLOAD__', true), true, 512, JSON_THROW_ON_ERROR);
            $allowed = ['app/Support/Deployment/DeploymentManager.php', 'Modules/Admin/Filament/Pages/ManageDeployment.php', 'resources/views/filament/manage-deployment.blade.php', '@public/assets/deployment.js', 'app/Support/Deployment/GitHubRelease.php'];
            if (array_keys($files) !== $allowed) {
                throw new RuntimeException('فایل تعمیر معتبر نیست.');
            }
            $backup = $root.'/deployments/handoff-repair-'.gmdate('Ymd-His').'-'.bin2hex(random_bytes(4));
            if (! mkdir($backup, 0700, true)) {
                throw new RuntimeException('ذخیرهٔ پشتیبان فایل‌های دیپلوی ممکن نیست.');
            }
            foreach ($files as $name => $contents) {
                $publicFile = str_starts_with($name, '@public/');
                $relative = $publicFile ? substr($name, 8) : $name;
                foreach ($publicFile ? $publics : $codes as $base) {
                    $path = $base.'/'.$relative;
                    if (! is_file($path) || is_link($path) || ! is_writable(dirname($path))) {
                        throw new RuntimeException('یکی از فایل‌های دیپلوی موجود یا قابل نوشتن نیست.');
                    }
                    $originals[$path] = file_get_contents($path);
                    $backups[$path] = $backup.'/'.count($originals).'.bak';
                    if (file_put_contents($backups[$path], $originals[$path], LOCK_EX) === false) {
                        throw new RuntimeException('ذخیرهٔ پشتیبان فایل‌های دیپلوی ممکن نیست.');
                    }
                    chmod($backups[$path], fileperms($path) & 0777);
                    $temp = $path.'.handoff-'.bin2hex(random_bytes(6)).'.tmp';
                    $temporary[$path] = $temp;
                    if (file_put_contents($temp, $contents, LOCK_EX) !== strlen($contents)) {
                        throw new RuntimeException('نوشتن فایل تعمیر ممکن نیست.');
                    }
                    chmod($temp, fileperms($path) & 0777);
                }
            }
            if (file_put_contents($backup.'/manifest.json', json_encode(array_keys($originals), JSON_THROW_ON_ERROR)) === false) {
                throw new RuntimeException('ثبت پشتیبان فایل‌های دیپلوی ممکن نیست.');
            }
            foreach ($temporary as $path => $temp) {
                if (! rename($temp, $path)) {
                    throw new RuntimeException('جایگزینی فایل دیپلوی ممکن نیست.');
                }
                $changed[] = $path;
                if (function_exists('opcache_invalidate')) {
                    opcache_invalidate($path, true);
                }
            }
            $success = true;
            $message = 'تعمیر انجام شد. به صفحهٔ به‌روزرسانی برگردید و دریافت و نصب را یک‌بار بزنید؛ مسیر دریافت جایگزین و نصب بسته از دستگاه در پنل فعال شد.';
        } catch (Throwable $exception) {
            foreach ($changed as $path) {
                rename($backups[$path], $path);
                if (function_exists('opcache_invalidate')) {
                    opcache_invalidate($path, true);
                }
            }
            $message = $exception instanceof RuntimeException ? $exception->getMessage() : 'تعمیر انجام نشد؛ دسترسی نوشتن فایل‌های برنامه را بررسی کنید.';
        } finally {
            foreach ($temporary as $temp) {
                if (is_file($temp)) {
                    unlink($temp);
                }
            }
            if (is_resource($lock)) {
                fclose($lock);
            }
        }
    }
}
?>
<!doctype html>
<html lang="fa" dir="rtl">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>تعمیر به‌روزرسانی</title><link rel="stylesheet" href="/assets/fonts/vazirmatn/font.css"><link rel="stylesheet" href="/assets/deployment.css"></head>
<body><main>
<h1>تعمیر به‌روزرسانی</h1>
<p>این ابزار فقط بخش به‌روزرسانی را اصلاح می‌کند و از فایل‌های قبلی آن پشتیبان می‌گیرد. دیتابیس، تنظیمات نصب و فایل‌های کاربران تغییر نمی‌کنند.</p>
<p role="status"><?= htmlspecialchars($message, ENT_QUOTES, 'UTF-8') ?></p>
<?php if (! $success) { ?>
<form method="post">
<input type="hidden" name="csrf" value="<?= htmlspecialchars($_SESSION['csrf'], ENT_QUOTES, 'UTF-8') ?>">
<label for="code">کد بازیابی ساخته‌شده در پنل</label><input id="code" name="code" type="password" required autocomplete="off" dir="ltr">
<button type="submit">اصلاح بخش به‌روزرسانی</button>
</form>
<?php } ?>
<p><a href="/admin/manage-deployment?handoff=2">بازگشت به به‌روزرسانی سایت</a></p>
</main></body></html>
