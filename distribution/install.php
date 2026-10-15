<?php

declare(strict_types=1);
use App\Support\Installation\WebInstaller;

$base = dirname(__DIR__).'/divaraap';
require $base.'/app/Support/Installation/WebInstaller.php';
$installer = new WebInstaller($base, __DIR__);
header('Cache-Control: no-store, private');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header("Content-Security-Policy: default-src 'self'; style-src 'self' 'unsafe-inline'; form-action 'self'; frame-ancestors 'none'; base-uri 'none'");
if (! $installer->available()) {
    http_response_code(404);
    exit;
}
$secure = ! empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
$local = PHP_SAPI === 'cli-server' && in_array(parse_url('http://'.($_SERVER['HTTP_HOST'] ?? ''), PHP_URL_HOST), ['localhost', '127.0.0.1'], true);
if (! $secure && ! $local) {
    http_response_code(400);
    exit('نصب را فقط از آدرس HTTPS دامنه باز کنید.');
}
session_name('divaraap_installer');
session_start(['cookie_httponly' => true, 'cookie_secure' => $secure, 'cookie_samesite' => 'Strict', 'use_strict_mode' => true]);
$_SESSION['csrf'] ??= bin2hex(random_bytes(32));
$error = '';
$success = false;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $submittedCsrf = is_string($_POST['csrf'] ?? null) ? $_POST['csrf'] : '';
    $submittedToken = is_string($_POST['token'] ?? null) ? $_POST['token'] : '';
    if (! hash_equals($_SESSION['csrf'], $submittedCsrf)) {
        http_response_code(419);
        $error = 'فرم منقضی شده؛ صفحه را دوباره باز کنید.';
    } elseif (! $installer->authorized($submittedToken)) {
        http_response_code(403);
        $error = 'کد نصب معتبر نیست.';
    } else {
        session_regenerate_id(true);
        try {
            if (function_exists('set_time_limit')) {
                set_time_limit(180);
            }
            $installer->install($_POST);
            $success = true;
            $_SESSION = [];
            session_destroy();
        } catch (Throwable $exception) {
            http_response_code(422);
            $error = $exception instanceof RuntimeException ? $exception->getMessage() : 'نصب انجام نشد؛ پیش‌نیازها و راهنمای بازیابی را بررسی کنید.';
        }
    }
}
$checks = $installer->requirements();
$escape = static fn (string $value): string => htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$fields = [
    'token' => ['کد اختصاصی نصب', 'password', ''],
    'site_name' => ['نام سایت', 'text', 'دیوارا'],
    'site_url' => ['آدرس سایت', 'url', ($secure ? 'https' : 'http').'://'.($_SERVER['HTTP_HOST'] ?? 'localhost')],
    'db_host' => ['میزبان دیتابیس', 'text', 'localhost'],
    'db_port' => ['پورت دیتابیس', 'number', '3306'],
    'db_name' => ['نام دیتابیس خالی', 'text', ''],
    'db_user' => ['نام کاربر دیتابیس', 'text', ''],
    'db_password' => ['رمز دیتابیس', 'password', ''],
    'admin_name' => ['نام مدیر', 'text', ''],
    'admin_email' => ['ایمیل مدیر', 'email', ''],
    'admin_password' => ['رمز مدیر؛ حداقل ۱۲ نویسه', 'password', ''],
    'admin_confirmation' => ['تکرار رمز مدیر', 'password', ''],
];
?>
<!doctype html>
<html lang="fa" dir="rtl">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>نصب دیوارا</title>
<link rel="stylesheet" href="/assets/fonts/vazirmatn/font.css">
<style>
*{box-sizing:border-box}body{margin:0;background:#f5f6f8;color:#202124;font-family:Vazirmatn,sans-serif;line-height:1.8}main{max-width:760px;margin:32px auto;padding:28px;background:#fff;border:1px solid #ddd;border-radius:18px}h1{margin:0;font-size:28px}p{color:#50535a}.checks{display:flex;flex-wrap:wrap;gap:8px;padding:0;list-style:none}.checks li{padding:3px 10px;border:1px solid #ccc;border-radius:6px;font-size:13px}.bad{color:#a31919}.grid{display:grid;grid-template-columns:1fr 1fr;gap:18px}label{display:block;font-weight:600}input{width:100%;font:inherit;padding:10px;border:1px solid #92969c;border-radius:8px;min-height:46px}input:focus-visible,a:focus-visible,button:focus-visible{outline:3px solid #2464c5;outline-offset:3px}button,.button{display:inline-block;border:0;border-radius:9px;background:#161616;color:#fff;padding:12px 24px;font:inherit;text-decoration:none;cursor:pointer;margin-top:24px;min-height:46px}.notice{padding:14px;border:1px solid #aaa;border-radius:8px}small{display:block;color:#50535a}@media(max-width:600px){main{margin:12px;padding:20px}.grid{grid-template-columns:1fr}}
</style>
</head>
<body><main>
<h1><?= $success ? 'نصب با موفقیت تمام شد' : 'نصب دیوارا' ?></h1>
<?php if ($success) { ?>
<p>حساب مدیر ساخته شد و نصاب قفل شد. با ایمیل و رمز انتخابی وارد مدیریت شوید.</p>
<p>پیش از استفادهٔ عمومی، تنظیمات ایمیل، صفحات قوانین و اطلاعات سایت را در پنل تکمیل کنید.</p>
<a class="button" href="/admin/login">ورود به مدیریت</a>
<?php } else { ?>
<p>اطلاعات دیتابیس خالی و حساب مدیر را وارد کنید. کد نصب در فایل خصوصی INSTALLATION-CODE.txt داخل بسته است.</p>
<ul class="checks" aria-label="پیش‌نیازهای هاست">
<?php foreach ($checks as $name => $ok) { ?><li class="<?= $ok ? '' : 'bad' ?>"><bdi><?= $escape($name) ?></bdi>: <?= $ok ? 'آماده' : 'ناموفق' ?></li><?php } ?>
</ul>
<?php if ($error !== '') { ?><p class="notice bad" role="alert"><?= $escape($error) ?></p><?php } ?>
<form method="post" autocomplete="off">
<input type="hidden" name="csrf" value="<?= $escape($_SESSION['csrf']) ?>">
<div class="grid">
<?php foreach ($fields as $key => [$label, $type, $default]) { ?>
<div><label for="<?= $key ?>"><?= $escape($label) ?></label>
<input id="<?= $key ?>" name="<?= $key ?>" type="<?= $type ?>" value="<?= $escape($type === 'password' ? '' : (string) ($_POST[$key] ?? $default)) ?>" required maxlength="512" dir="<?= in_array($key, ['site_name', 'admin_name'], true) ? 'auto' : 'ltr' ?>" <?= str_starts_with($key, 'admin_') && $type === 'password' ? 'minlength="12" autocomplete="new-password"' : '' ?>>
</div>
<?php } ?>
</div>
<small>فقط نصب تازه پشتیبانی می‌شود. هیچ جدول یا دادهٔ قبلی حذف نمی‌شود. هنگام نصب صفحه را نبندید.</small>
<button type="submit" <?= in_array(false, $checks, true) ? 'disabled' : '' ?>>نصب سایت</button>
</form>
<?php } ?>
</main></body></html>
