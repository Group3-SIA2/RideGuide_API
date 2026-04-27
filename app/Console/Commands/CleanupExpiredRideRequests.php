<?php

namespace App\Console\Commands;

use App\Models\CommuterRideRequest;
use Illuminate\Console\Command;

class CleanupExpiredRideRequests extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'ride-requests:cleanup-expired';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Mark or delete ride requests that have expired (10 minutes without driver acceptance)';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Starting cleanup of expired ride requests...');

        // Find all active requests past expiration and mark as 'expired'
        $expiredCount = CommuterRideRequest::where('expires_at', '<=', now())
            ->where('status', 'active')
            ->update(['status' => 'expired']);

        if ($expiredCount > 0) {
            $this->info("Successfully marked {$expiredCount} requests as expired");
        } else {
            $this->info('No expired requests found to clean up');
        }

        return Command::SUCCESS;
    }
}
