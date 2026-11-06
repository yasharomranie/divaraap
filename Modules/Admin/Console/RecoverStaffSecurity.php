<?php

declare(strict_types=1);

namespace Modules\Admin\Console;

use Illuminate\Console\Command;
use Modules\Admin\Models\StaffSecurity;

final class RecoverStaffSecurity extends Command
{
    protected $signature = 'staff-security:recover {user} {--confirm}';

    protected $description = 'Reset staff MFA after operator identity verification; account password is preserved.';

    public function handle(StaffSecurity $security): int
    {
        $id = filter_var($this->argument('user'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if (! $this->option('confirm') || $id === false) {
            $this->error('A valid user ID and --confirm are required. Verify the account owner before running this command.');

            return self::INVALID;
        }
        $security->recover($id);
        $this->info('Staff MFA reset. Existing MFA proofs and remember tokens are invalid. Password login and fresh enrollment are required when policy applies.');

        return self::SUCCESS;
    }
}
