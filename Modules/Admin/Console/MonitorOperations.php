<?php

declare(strict_types=1);

namespace Modules\Admin\Console;

use Illuminate\Console\Command;
use Modules\Admin\Models\OperationalMonitor;
use Throwable;

final class MonitorOperations extends Command
{
    protected $signature = 'operations:monitor {--scheduled}';

    protected $description = 'Sample enabled operational checks without repairing services or sending test mail.';

    public function handle(): int
    {
        try {
            $result = OperationalMonitor::sample((bool) $this->option('scheduled'));
            $this->info($result);

            return self::SUCCESS;
        } catch (Throwable) {
            $this->error('Monitoring unavailable. Check private server logs and database access.');

            return self::FAILURE;
        }
    }
}
