<?php

declare(strict_types=1);

namespace App\Support\Deployment;

use Modules\Site\App\Settings\DeploymentSettings;
use RuntimeException;

final class DeploymentManager
{
    public function layout(): ReleaseLayout
    {
        $code = dirname(__DIR__, 3);
        $roots = [$code];
        if (basename(dirname($code)) === 'releases' && preg_match('/\A[a-f0-9]{32}\z/', basename($code))) {
            $roots[] = dirname($code, 2);
        }
        $roots[] = config('deployment.root');
        foreach ($roots as $root) {
            if (is_string($root) && is_file($root.'/installed.lock')) {
                $root = realpath($root);

                return new ReleaseLayout($root, dirname($root).'/public_html');
            }
        }
        throw new RuntimeException('Deployment is available only in the shared-hosting installation package.');
    }

    public function prepare(): array
    {
        $layout = $this->layout();
        $lock = $layout->lock();
        try {
            if (is_file($layout->root.'/deployment-maintenance.json')) {
                throw new RuntimeException('Recover the interrupted deployment before starting another.');
            }
            if (! is_file($layout->root.'/recovery.token')) {
                throw new RuntimeException('Create and save a recovery code before deploying.');
            }
            $previousJob = $layout->read('deployment-job.json');
            if (($previousJob['state'] ?? null) === 'prepared' && ($previousJob['expires'] ?? 0) > time()) {
                try {
                    $layout->code($previousJob['id']);
                } catch (RuntimeException $exception) {
                    if ($exception->getMessage() !== 'Release is incomplete.') {
                        throw $exception;
                    }
                    $previousJob['state'] = 'failed';
                    $previousJob['expires'] = 0;
                    $layout->write('deployment-job.json', $previousJob);
                }
            }
            if (($previousJob['state'] ?? null) === 'prepared' && ($previousJob['expires'] ?? 0) > time()) {
                $token = bin2hex(random_bytes(32));
                $previousJob['token_hash'] = hash('sha256', $token);
                $previousJob['status_token_hash'] = hash('sha256', $token);
                $previousJob['expires'] = time() + 600;
                $layout->write('deployment-job.json', $previousJob);

                return ['id' => $previousJob['id'], 'token' => $token];
            }
            $layout->pruneUnused();
            if (disk_free_space($layout->root) < 1800000000) {
                throw new RuntimeException('At least 1.8 GB of free disk space is required.');
            }
            $settings = app(DeploymentSettings::class);
            $id = bin2hex(random_bytes(16));
            $directory = $layout->root.'/deployments';
            if (! is_dir($directory)) {
                mkdir($directory, 0700, true);
            }
            $archive = $directory.'/'.$id.'.zip';
            try {
                $release = app(GitHubRelease::class)->download($settings->repository, $settings->ref, $settings->github_token, $archive);
                app(ReleaseArchive::class)->extract($archive, $layout, $id, config('app.url'));
            } finally {
                if (is_file($archive)) {
                    unlink($archive);
                }
            }
            $token = bin2hex(random_bytes(32));
            $layout->write('deployment-job.json', ['id' => $id, 'token_hash' => hash('sha256', $token), 'status_token_hash' => hash('sha256', $token), 'expires' => time() + 600, 'state' => 'prepared', 'release' => $release]);

            return ['id' => $id, 'token' => $token];
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }

    public function startUpload(int $size, string $checksum, int $owner): string
    {
        if ($owner < 1 || $size < 1 || $size > 268435456 || ! preg_match('/\A[a-f0-9]{64}\z/i', $checksum)) {
            throw new RuntimeException('Invalid uploaded package.');
        }
        $layout = $this->layout();
        $lock = $layout->lock();
        try {
            $this->uploadReady($layout);
            $directory = $layout->root.'/deployments';
            if (! is_dir($directory)) {
                mkdir($directory, 0700, true);
            }
            $metadata = 'deployments/upload-'.$owner.'.json';
            $old = $layout->read($metadata);
            if (preg_match('/\A[a-f0-9]{32}\z/', $old['id'] ?? '')) {
                $previous = $directory.'/upload-'.$old['id'].'.part';
                if (is_file($previous)) {
                    unlink($previous);
                }
            }
            $id = bin2hex(random_bytes(16));
            $path = $directory.'/upload-'.$id.'.part';
            if (file_put_contents($path, '') === false) {
                throw new RuntimeException('Cannot write uploaded package.');
            }
            chmod($path, 0600);
            $layout->write($metadata, ['id' => $id, 'size' => $size, 'checksum' => strtolower($checksum), 'expires' => time() + 3600]);

            return $id;
        } finally {
            fclose($lock);
        }
    }

    public function uploadChunk(string $id, int $offset, string $encoded, int $owner): int
    {
        if (strlen($encoded) > 350000 || $offset < 0) {
            throw new RuntimeException('Invalid uploaded package.');
        }
        $bytes = base64_decode($encoded, true);
        if ($bytes === false || strlen($bytes) < 1 || strlen($bytes) > 262144) {
            throw new RuntimeException('Invalid uploaded package.');
        }
        $layout = $this->layout();
        $lock = $layout->lock();
        try {
            $this->uploadReady($layout);
            [$metadata, $path] = $this->uploadedFile($layout, $id, $owner);
            clearstatcache(true, $path);
            if (filesize($path) !== $offset || $offset + strlen($bytes) > $metadata['size']) {
                throw new RuntimeException('Upload offset mismatch.');
            }
            if (file_put_contents($path, $bytes, FILE_APPEND | LOCK_EX) !== strlen($bytes)) {
                throw new RuntimeException('Cannot write uploaded package.');
            }

            return $offset + strlen($bytes);
        } finally {
            fclose($lock);
        }
    }

    public function finishUpload(string $id, int $owner): array
    {
        $layout = $this->layout();
        $lock = $layout->lock();
        $path = null;
        try {
            $this->uploadReady($layout);
            [$metadata, $path] = $this->uploadedFile($layout, $id, $owner);
            clearstatcache(true, $path);
            if (filesize($path) !== $metadata['size'] || ! hash_equals($metadata['checksum'], hash_file('sha256', $path))) {
                throw new RuntimeException('Uploaded package checksum mismatch.');
            }
            $layout->pruneUnused();
            $releaseId = bin2hex(random_bytes(16));
            app(ReleaseArchive::class)->extract($path, $layout, $releaseId, config('app.url'));
            $token = bin2hex(random_bytes(32));
            $layout->write('deployment-job.json', ['id' => $releaseId, 'token_hash' => hash('sha256', $token), 'status_token_hash' => hash('sha256', $token), 'expires' => time() + 600, 'state' => 'prepared', 'release' => ['sha256' => $metadata['checksum'], 'transport' => 'admin_upload']]);

            return ['id' => $releaseId, 'token' => $token];
        } finally {
            if ($path !== null && is_file($path)) {
                unlink($path);
                $metadataFile = $layout->root.'/deployments/upload-'.$owner.'.json';
                if (is_file($metadataFile)) {
                    unlink($metadataFile);
                }
            }
            fclose($lock);
        }
    }

    private function uploadReady(ReleaseLayout $layout): void
    {
        if (is_file($layout->root.'/deployment-maintenance.json')) {
            throw new RuntimeException('Recover the interrupted deployment before starting another.');
        }
        if (! is_file($layout->root.'/recovery.token')) {
            throw new RuntimeException('Create and save a recovery code before deploying.');
        }
        if (disk_free_space($layout->root) < 1800000000) {
            throw new RuntimeException('At least 1.8 GB of free disk space is required.');
        }
    }

    private function uploadedFile(ReleaseLayout $layout, string $id, int $owner): array
    {
        if ($owner < 1 || ! preg_match('/\A[a-f0-9]{32}\z/', $id)) {
            throw new RuntimeException('Invalid uploaded package.');
        }
        $metadata = $layout->read('deployments/upload-'.$owner.'.json');
        $path = $layout->root.'/deployments/upload-'.$id.'.part';
        if (($metadata['id'] ?? '') !== $id || ($metadata['expires'] ?? 0) < time() || ! is_file($path)) {
            throw new RuntimeException('Invalid uploaded package.');
        }

        return [$metadata, $path];
    }

    public function status(): array
    {
        $job = $this->layout()->read('deployment-job.json');
        $messages = [
            'prepared' => 'بسته آماده است؛ برای ادامهٔ نصب، دکمهٔ دریافت و نصب را بزنید. دانلود دوباره انجام نمی‌شود.',
            'running' => 'نصب روی سرور شروع شده است. دوباره نصب را شروع نکنید؛ وضعیت را بررسی کنید.',
            'completed' => 'آخرین نصب با موفقیت تمام شده است.',
            'failed' => 'نصب قبلی متوقف شده است. پیش از تلاش مجدد، صفحهٔ بازیابی را بررسی کنید.',
            'recovered' => 'بازیابی ثبت شده است؛ می‌توانید وضعیت سایت را بررسی کنید.',
        ];
        $state = $job['state'] ?? '';
        if ($state === 'prepared' && ($job['expires'] ?? 0) <= time()) {
            $messages['prepared'] = 'مهلت بستهٔ آماده تمام شده است؛ دکمهٔ دریافت و نصب، نسخه را دوباره بررسی و دریافت می‌کند.';
        }

        return isset($messages[$state]) ? ['state' => $state, 'message' => $messages[$state]] : [];
    }

    public function recoveryCode(): string
    {
        $layout = $this->layout();
        $lock = $layout->lock();
        try {
            $token = bin2hex(random_bytes(32));
            $layout->write('recovery.token', ['hash' => hash('sha256', $token)]);

            return $token;
        } finally {
            fclose($lock);
        }
    }

    public function rollback(): void
    {
        $layout = $this->layout();
        $lock = $layout->lock();
        try {
            $layout->rollback();
        } finally {
            fclose($lock);
        }
    }
}
