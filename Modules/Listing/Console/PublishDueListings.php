<?php

declare(strict_types=1);

namespace Modules\Listing\Console;

use Illuminate\Console\Command;
use Modules\Listing\Models\Listing;

final class PublishDueListings extends Command
{
    protected $signature = 'listings:publish-due';

    protected $description = 'Publish approved listings whose scheduled time has arrived';

    public function handle(): int
    {
        $this->line((string) Listing::publishDue(200));

        return self::SUCCESS;
    }
}
