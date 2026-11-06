<?php

declare(strict_types=1);

namespace Modules\Notification\Console;

use Illuminate\Console\Command;
use Modules\Notification\Models\EmailDelivery;

final class DeliverNotificationEmails extends Command
{
    protected $signature = 'notifications:deliver-email {--limit=50}';

    protected $description = 'Deliver consented notification emails with bounded daily quotas.';

    public function handle(): int
    {
        $limit = filter_var($this->option('limit'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 200]]);
        if ($limit === false) {
            $this->error('Limit must be between 1 and 200.');

            return self::INVALID;
        }
        $processed = EmailDelivery::deliverBatch($limit);
        $this->info('Processed: '.$processed);

        return self::SUCCESS;
    }
}
