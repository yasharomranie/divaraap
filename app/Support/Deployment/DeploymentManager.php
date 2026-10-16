<?php

declare(strict_types=1);

namespace App\Support\Deployment;

use Modules\Site\App\Settings\DeploymentSettings;
use RuntimeException;

final class DeploymentManager
{
    public function layout(): ReleaseLayout
    {
        $root = config('deployment.root');
        if (! is_string($root) || ! is_file($root.'/installed.lock')) {
            throw new RuntimeException('Deployment is available only in the shared-hosting installation package.');
        }

        return new ReleaseLayout($root, dirname($root).'/public_html');
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
                $layout->code($previousJob['id']);
                $token = bin2hex(random_bytes(32));
                $previousJob['token_hash'] = hash('sha256', $token);
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
            $layout->write('deployment-job.json', ['id' => $id, 'token_hash' => hash('sha256', $token), 'expires' => time() + 600, 'state' => 'prepared', 'release' => $release]);

            return ['id' => $id, 'token' => $token];
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
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
