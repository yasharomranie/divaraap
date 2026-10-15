<?php

declare(strict_types=1);

header('Cache-Control: no-store, private');
header('Content-Type: text/html; charset=utf-8');
header('X-Frame-Options: DENY');
header('X-Content-Type-Options: nosniff');
header("Content-Security-Policy: default-src 'none'; style-src 'self' 'unsafe-inline'; font-src 'self'; form-action 'self'; frame-ancestors 'none'; base-uri 'none'");
$secure = ! empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
$local = PHP_SAPI === 'cli-server' && in_array(parse_url('http://'.($_SERVER['HTTP_HOST'] ?? ''), PHP_URL_HOST), ['localhost', '127.0.0.1'], true);
if (! $secure && ! $local) {
    http_response_code(403);
    exit('HTTPS is required.');
}
$root = dirname(__DIR__).'/divaraap';
if (! is_file($root.'/install.token') || ! is_file($root.'/installation.pending') || is_file($root.'/installed.lock')) {
    http_response_code(404);
    exit;
}
session_name('divaraap_install_diagnostics');
session_start(['cookie_httponly' => true, 'cookie_secure' => $secure, 'cookie_samesite' => 'Strict', 'use_strict_mode' => true]);
$_SESSION['csrf'] ??= bin2hex(random_bytes(32));
$csrf = $_SESSION['csrf'];
session_write_close();
$report = [];
$error = '';
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    if (! is_string($_POST['csrf'] ?? null) || ! hash_equals($csrf, $_POST['csrf'])
        || ! is_string($_POST['token'] ?? null) || ! hash_equals(trim(file_get_contents($root.'/install.token')), hash('sha256', $_POST['token']))) {
        http_response_code(403);
        $error = 'کد نصب یا درخواست معتبر نیست.';
    } else {
        $lock = fopen($root.'/installation.mutex', 'c');
        if (! $lock || ! flock($lock, LOCK_EX | LOCK_NB)) {
            http_response_code(409);
            $error = 'نصب دیگری در حال اجراست؛ کمی بعد تلاش کنید.';
        } else {
            $report = ['Diagnostics revision' => '2', 'PHP' => PHP_VERSION, 'Environment file' => is_file($root.'/.env') ? 'present' : 'missing', 'Temporary environment file' => is_file($root.'/.env.installing') ? 'present' : 'missing', 'Disabled functions' => ini_get('disable_functions') ?: 'none', 'Open basedir' => ini_get('open_basedir') ?: 'none'];
            $secrets = [];
            try {
                if (is_file($root.'/.env')) {
                    require $root.'/vendor/autoload.php';
                    foreach (Dotenv\Dotenv::parse(file_get_contents($root.'/.env')) as $key => $value) {
                        if (preg_match('/PASSWORD|SECRET|TOKEN|KEY/', $key) && is_string($value) && $value !== '') {
                            $secrets[] = $value;
                        }
                    }
                    $app = require $root.'/bootstrap/app.php';
                    $app->usePublicPath(__DIR__);
                    $app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
                    $report['Bootstrap'] = 'passed; migrations were not executed';
                    $connection = $app['db']->connection();
                    $report['Configured table engine'] = $connection->getConfig('engine');
                    $report['Configuration cached'] = $app->configurationIsCached();
                    $report['InnoDB declarations in config file'] = substr_count(file_get_contents($root.'/config/database.php'), "'engine' => 'InnoDB'");
                    $pdo = $connection->getPdo();
                    $report['Database settings'] = $pdo->query('SELECT DATABASE() AS database_name, VERSION() AS version, @@default_storage_engine AS server_default_engine, @@sql_mode AS sql_mode, @@innodb_default_row_format AS innodb_default_row_format, @@innodb_page_size AS innodb_page_size')->fetch(PDO::FETCH_ASSOC);
                    $report['Tables'] = $pdo->query('SELECT TABLE_NAME AS name, ENGINE AS engine, ROW_FORMAT AS row_format FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() ORDER BY TABLE_NAME')->fetchAll(PDO::FETCH_ASSOC);
                    $names = array_column($report['Tables'], 'name');
                    foreach (['users', 'profiles', 'password_reset_tokens'] as $table) {
                        if (in_array($table, $names, true)) {
                            $report['Table definitions'][$table] = $pdo->query('SHOW CREATE TABLE `'.$table.'`')->fetch(PDO::FETCH_NUM)[1];
                        }
                    }

                    $completed = in_array('migrations', $names, true) ? $pdo->query('SELECT migration FROM migrations ORDER BY id')->fetchAll(PDO::FETCH_COLUMN) : [];
                    $report['Completed migrations'] = $completed;
                    $migrator = $app['migrator'];
                    $files = $migrator->getMigrationFiles(array_merge([$app->databasePath('migrations')], $migrator->paths()));
                    foreach ($files as $migration => $path) {
                        if (! in_array($migration, $completed, true)) {
                            $report['First uncompleted migration'] = $migration;
                            break;
                        }
                    }

                } else {
                    $report['Bootstrap'] = 'not attempted; environment file missing';
                }
            } catch (Throwable $exception) {
                $report['Exception'] = get_class($exception);
                $report['File'] = str_replace($root.'/', '', $exception->getFile());
                $report['Line'] = (string) $exception->getLine();
                if ($exception instanceof ErrorException && preg_match('/\A(?:file_exists|is_file|is_dir|file_get_contents|file_put_contents|fopen|mkdir|scandir|realpath|include|require|opendir|tempnam|rename|unlink|chmod|readlink|symlink)\(/', $exception->getMessage())) {
                    $report['Filesystem warning'] = str_replace($secrets, '[redacted]', $exception->getMessage());
                }
            } finally {
                flock($lock, LOCK_UN);
                fclose($lock);
            }
        }
    }
}
?>
<!doctype html><html lang="fa" dir="rtl"><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>بررسی نصب ناتمام</title><link rel="stylesheet" href="/assets/fonts/vazirmatn/font.css"><link rel="stylesheet" href="/assets/deployment.css"><main><h1>بررسی نصب ناتمام</h1><p>کد خصوصی نصب را وارد کنید. این بررسی نصب را تکرار نمی‌کند و مهاجرت یا حذف جدول اجرا نمی‌کند. پس از رفع مشکل این فایل را از پوشهٔ عمومی حذف کنید.</p><p role="alert"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></p><form method="post"><input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>"><label for="token">کد خصوصی نصب</label><input id="token" name="token" type="password" required autocomplete="off" dir="ltr"><button type="submit">بررسی خطا</button></form><?php if ($report !== []) { ?><h2>نتیجهٔ بررسی</h2><pre dir="ltr" style="white-space:pre-wrap;overflow-wrap:anywhere"><?= htmlspecialchars(json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8') ?></pre><?php } ?></main></html>
