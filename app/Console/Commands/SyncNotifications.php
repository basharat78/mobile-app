<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\User;
use App\Services\SyncService;
use Illuminate\Support\Facades\Log;

class SyncNotifications extends Command
{
    protected $signature = 'app:sync-notifications {email}';
    protected $description = 'Perform a background sync of loads, bids, and statuses using the master SyncService.';

    public function handle()
    {
        $email = $this->argument('email');
        Log::info("SyncNotifications (Background): Starting process for {$email}");

        $user = User::where('email', $email)->first();

        if (!$user) {
            Log::error("SyncNotifications (Background): User not found for email: {$email}");
            return Command::FAILURE;
        }

        // --- RELIABILITY UPGRADE (v73) ---
        // We now call the exact same 'SyncService' used by the foreground heartbeat.
        // This ensures bug-parity and consistent alerting between 'App Open' and 'App Closed'.
        $result = SyncService::performGlobalSync($user);

        if ($result['status'] === 'success') {
            Log::info("SyncNotifications (Background): Completed successfully for {$email}", $result['data']);
            return Command::SUCCESS;
        } else {
            Log::error("SyncNotifications (Background): Failed for {$email}. Reason: " . ($result['message'] ?? 'Unknown Error'));
            return Command::FAILURE;
        }
    }
}
