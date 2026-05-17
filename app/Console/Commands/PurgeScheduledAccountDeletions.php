<?php

namespace App\Console\Commands;

use App\Services\AccountDeletionService;
use Illuminate\Console\Command;

class PurgeScheduledAccountDeletions extends Command
{
    protected $signature = 'accounts:purge-scheduled-deletions';

    protected $description = 'Permanently delete user accounts whose deletion grace period has ended';

    public function handle(AccountDeletionService $accountDeletionService): int
    {
        $count = $accountDeletionService->purgeExpiredDeletions();

        $this->info("Purged {$count} scheduled account(s).");

        return self::SUCCESS;
    }
}
