<?php

declare(strict_types=1);

namespace App\Support\Deployment;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Throwable;

final class ReleaseRunner
{
    public function __construct(private readonly DatabaseBackup $backup) {}

    public function run(ReleaseLayout $layout, array $job): array
    {
        $steps = [];
        $migrating = false;
        try {
            $job['step'] = 'database_backup';
            $layout->write('deployment-job.json', $job);
            $this->backup->create(DB::connection()->getPdo(), $layout->root.'/deployments/'.$job['id'].'.sql');
            $steps[] = 'پشتیبان پایگاه داده ذخیره شد.';
            $commands = [
                ['migrate', ['--force' => true]],
                ['migrate', ['--force' => true, '--path' => 'database/settings']],
                ['config:clear', []],
                ['route:clear', []],
                ['view:clear', []],
                ['config:cache', []],
                ['route:cache', []],
                ['view:cache', []],
            ];
            $migrating = true;
            foreach ($commands as [$command, $arguments]) {
                $job['step'] = $command;
                $layout->write('deployment-job.json', $job);
                if (Artisan::call($command, $arguments) !== 0) {
                    throw new RuntimeException('Framework command failed.');
                }
                $job['output'][$command] = substr(Artisan::output(), -16000);
                $steps[] = $command.' ✓';
            }
            $job['step'] = 'activate';
            $layout->write('deployment-job.json', $job);
            $layout->activate($job['id']);
            $job['state'] = 'completed';
            $job['steps'] = $steps;
            unset($job['token_hash']);
            $layout->write('deployment-job.json', $job);
            unlink($layout->root.'/deployment-maintenance.json');

            return ['ok' => true, 'message' => 'نسخهٔ جدید فعال شد.', 'steps' => $steps];
        } catch (Throwable $exception) {
            $job['state'] = 'failed';
            $job['error_type'] = get_class($exception);
            $job['error'] = substr($exception->getMessage(), 0, 16000);
            unset($job['token_hash']);
            $layout->write('deployment-job.json', $job);
            if (! $migrating && is_file($layout->root.'/deployment-maintenance.json')) {
                unlink($layout->root.'/deployment-maintenance.json');
            }

            return ['ok' => false, 'message' => 'مرحلهٔ '.$job['step'].' ناموفق بود. از صفحهٔ بازیابی استفاده کنید؛ جزئیات در فایل خصوصی deployment-job.json ثبت شده است.', 'steps' => $steps];
        }
    }
}
