<?php

namespace App\Livewire;

use Livewire\Volt\Component;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use App\Services\SyncService;

new class extends Component
{
    /**
     * v89: Track if this is the first pulse to avoid flooding the user
     * with old notifications from previous sessions.
     */
    public $isFirstPulse = true;

    /**
     * The Heartbeat: This runs globally on every page thanks to app.blade.php.
     * It ensures the local database is always fresh and triggers alerts even
     * if the user is not on the 'Find' or 'Dashboard' pages.
     */
    public function performGlobalHeartbeat()
    {
        if (!Auth::check()) return;

        $user = Auth::user();
        
        // Only heartbeat for carrier roles
        if ($user->role !== 'carrier') return;

        Log::info("GlobalHeartbeat: Pulsing sync for User #{$user->id} (FirstPulse: " . ($this->isFirstPulse ? 'TRUE' : 'FALSE') . ")");
        
        // Perform sync. First run is 'silent' to prevent old cloud records from popping alerts.
        $result = SyncService::performGlobalSync($user, $this->isFirstPulse);

        // If sync discovered new database notifications, we dispatch them for toasts
        $this->checkLocalNotifications($user);

        // Mark first pulse as done
        $this->isFirstPulse = false;
    }

    protected function checkLocalNotifications($user)
    {
        $notifications = $user->unreadNotifications()->get();

        foreach ($notifications as $notification) {
            // Only toast if NOT the first pulse
            if (!$this->isFirstPulse) {
                $this->dispatch('new-notification', 
                    title: $notification->data['title'] ?? 'Notification',
                    message: $notification->data['message'] ?? '',
                    type: $notification->data['type'] ?? 'info'
                );
            }

            $notification->markAsRead();
        }
    }
};
?>

<div wire:poll.30s="performGlobalHeartbeat" class="hidden">
    <!-- Watchtower: Active -->
    <span class="sr-only">App-wide synchronization heartbeat active.</span>
</div>
