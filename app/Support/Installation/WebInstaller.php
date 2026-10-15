<?php

declare(strict_types=1);

namespace App\Support\Installation;

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Artisan;
use PDO;
use RuntimeException;
use Throwable;

final class WebInstaller
{
    public function __construct(private string $basePath, private string $publicPath) {}

    public function available(): bool
    {
        return is_file($this->basePath.'/install.token')
            && ! is_file($this->basePath.'/installed.lock')
            && (! is_file($this->basePath.'/.env') || is_file($this->basePath.'/installation.pending'));
    }

    public function requirements(): array
    {
        $checks = ['PHP 8.4+' => PHP_VERSION_ID >= 80400];
        foreach (['pdo_mysql', 'mbstring', 'intl', 'gd', 'fileinfo', 'zip', 'bcmath', 'dom', 'xml', 'ctype', 'iconv', 'openssl'] as $extension) {
            $checks[$extension] = extension_loaded($extension);
        }
        $checks['WebP'] = function_exists('imagewebp');
        foreach (['storage', 'bootstrap/cache'] as $path) {
            $checks[$path] = is_dir($this->basePath.'/'.$path) && is_writable($this->basePath.'/'.$path);
        }
        $checks['environment'] = is_writable($this->basePath);

        return $checks;
    }

    public function authorized(string $token): bool
    {
        return $this->available() && hash_equals(trim((string) file_get_contents($this->basePath.'/install.token')), hash('sha256', $token));
    }

    public function install(array $input): void
    {
        if (! $this->authorized((string) ($input['token'] ?? ''))) {
            throw new RuntimeException('کد نصب معتبر نیست.');
        }
        if (in_array(false, $this->requirements(), true)) {
            throw new RuntimeException('پیش‌نیازهای ناموفق باید ابتدا در هاست فعال شوند.');
        }
        $this->validate($input);
        $mutex = fopen($this->basePath.'/installation.mutex', 'c');
        if (! $mutex || ! flock($mutex, LOCK_EX | LOCK_NB)) {
            throw new RuntimeException('یک نصب دیگر در حال اجراست.');
        }
        $step = 'بررسی دیتابیس';
        try {
            if (! $this->available() || is_file($this->basePath.'/installation.pending')) {
                throw new RuntimeException('نصب قبلی ناتمام مانده است؛ راهنمای بازیابی داخل بسته را بررسی کنید.');
            }
            $this->assertEmptyDatabase($input);
            $settings = parse_ini_file($this->basePath.'/.env.shared.example', false, INI_SCANNER_RAW);
            if (! is_array($settings)) {
                throw new RuntimeException('نمونهٔ تنظیمات بسته موجود نیست.');
            }
            $settings = array_replace($settings, [
                'APP_NAME' => $input['site_name'],
                'APP_URL' => rtrim($input['site_url'], '/'),
                'APP_KEY' => 'base64:'.base64_encode(random_bytes(32)),
                'DB_CONNECTION' => 'mysql',
                'DB_HOST' => $input['db_host'],
                'DB_PORT' => (string) $input['db_port'],
                'DB_DATABASE' => $input['db_name'],
                'DB_USERNAME' => $input['db_user'],
                'DB_PASSWORD' => $input['db_password'],
                'SESSION_SECURE_COOKIE' => str_starts_with($input['site_url'], 'https://') ? 'true' : 'false',
            ]);
            if (file_put_contents($this->basePath.'/installation.pending', date(DATE_ATOM), LOCK_EX) === false) {
                throw new RuntimeException('ذخیرهٔ وضعیت نصب ممکن نیست.');
            }
            $environment = '';
            foreach ($settings as $key => $value) {
                $value = (string) $value;
                if (preg_match('/[\r\n\x00]/', $value)) {
                    throw new RuntimeException('مقدار تنظیمات نامعتبر است.');
                }
                $environment .= $key.'="'.str_replace(['\\', '"', '$'], ['\\\\', '\\"', '\\$'], $value).'"'.PHP_EOL;
                putenv($key.'='.$value);
                $_ENV[$key] = $value;
                $_SERVER[$key] = $value;
            }
            $temp = $this->basePath.'/.env.installing';
            if (file_put_contents($temp, $environment, LOCK_EX) === false) {
                throw new RuntimeException('نوشتن تنظیمات ممکن نیست.');
            }
            chmod($temp, 0600);
            if (! rename($temp, $this->basePath.'/.env')) {
                throw new RuntimeException('ذخیرهٔ تنظیمات ممکن نیست.');
            }
            require_once $this->basePath.'/vendor/autoload.php';
            $app = require $this->basePath.'/bootstrap/app.php';
            $app->usePublicPath($this->publicPath);
            $app->make(Kernel::class)->bootstrap();
            $step = 'ساخت جدول‌ها';
            $this->command('migrate', ['--force' => true]);
            $this->command('migrate', ['--path' => 'database/settings', '--force' => true]);
            $step = 'تنظیم سایت و مدیر';
            InstallationData::initialize($input);
            $step = 'بازسازی کش';
            $this->command('config:cache');
            $this->command('route:cache');
            $this->command('view:cache');
            if (file_put_contents($this->basePath.'/installed.lock', date(DATE_ATOM), LOCK_EX) === false) {
                throw new RuntimeException('قفل نصب ذخیره نشد.');
            }
            unlink($this->basePath.'/install.token');
            unlink($this->basePath.'/installation.pending');
        } catch (Throwable $exception) {
            if (is_file($this->basePath.'/installation.pending')) {
                file_put_contents($this->basePath.'/installation-error.log', $step.' '.get_class($exception).' '.date(DATE_ATOM).PHP_EOL, FILE_APPEND | LOCK_EX);
                throw new RuntimeException('نصب در مرحلهٔ «'.$step.'» متوقف شد. سایت هنوز فعال نشده؛ راهنمای بازیابی را بخوانید.', previous: $exception);
            }
            throw $exception;
        } finally {
            flock($mutex, LOCK_UN);
            fclose($mutex);
        }
    }

    private function command(string $name, array $options = []): void
    {
        if (Artisan::call($name, $options) !== 0) {
            throw new RuntimeException('Installation command failed.');
        }
    }

    private function validate(array $input): void
    {
        foreach (['site_name', 'site_url', 'db_host', 'db_name', 'db_user', 'db_password', 'admin_name', 'admin_email', 'admin_password', 'admin_confirmation'] as $key) {
            if (! isset($input[$key]) || ! is_string($input[$key]) || $input[$key] === '' || strlen($input[$key]) > 512 || preg_match('/[\r\n\x00]/', $input[$key])) {
                throw new RuntimeException('همهٔ فیلدها را با مقدار معتبر پر کنید.');
            }
        }
        if (! filter_var($input['admin_email'], FILTER_VALIDATE_EMAIL) || strlen($input['admin_password']) < 12 || $input['admin_password'] !== $input['admin_confirmation']) {
            throw new RuntimeException('ایمیل مدیر یا تأیید رمز معتبر نیست؛ رمز حداقل ۱۲ نویسه باشد.');
        }
        $url = parse_url($input['site_url']);
        if (! filter_var($input['site_url'], FILTER_VALIDATE_URL) || ! in_array($url['scheme'] ?? '', ['http', 'https'], true) || (isset($url['user']) || isset($url['pass'])) || ! empty($url['query']) || ! empty($url['fragment']) || ! in_array($url['path'] ?? '', ['', '/'], true)) {
            throw new RuntimeException('آدرس سایت باید دامنهٔ اصلی بدون مسیر، با http یا https باشد.');
        }
        if (($url['scheme'] ?? '') !== 'https' && ! in_array($url['host'] ?? '', ['localhost', '127.0.0.1'], true)) {
            throw new RuntimeException('برای دامنهٔ عمومی، آدرس HTTPS لازم است.');
        }
        foreach (['db_host' => '/^[a-zA-Z0-9._-]+$/', 'db_name' => '/^[a-zA-Z0-9_$-]+$/', 'db_user' => '/^[a-zA-Z0-9_$-]+$/'] as $key => $pattern) {
            if (! preg_match($pattern, $input[$key])) {
                throw new RuntimeException('نام میزبان، دیتابیس یا کاربر معتبر نیست.');
            }
        }
        if (! filter_var($input['db_port'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 65535]])) {
            throw new RuntimeException('پورت دیتابیس معتبر نیست.');
        }
    }

    private function assertEmptyDatabase(array $input): void
    {
        try {
            $pdo = new PDO('mysql:host='.$input['db_host'].';port='.$input['db_port'].';dbname='.$input['db_name'].';charset=utf8mb4', $input['db_user'], $input['db_password'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_TIMEOUT => 10]);
            if ($pdo->query('SHOW TABLES')->fetchColumn() !== false) {
                throw new RuntimeException('فقط دیتابیس کاملاً خالی پذیرفته می‌شود؛ برای حفظ اطلاعات، نصب روی دیتابیس موجود انجام نشد.');
            }
        } catch (\PDOException) {
            throw new RuntimeException('اتصال دیتابیس برقرار نشد؛ نام دیتابیس، کاربر، رمز و دسترسی کاربر را بررسی کنید.');
        }
    }
}
