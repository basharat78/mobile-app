<?php

namespace App\Observers;

use App\Models\Load;
use Vendor\LocalNotification\Facades\LocalNotification;

class LoadObserver
{
    /**
     * Handle the Load "saved" event (covers created and updated).
     */
    public function saved(Load $load): void
    {
        \Illuminate\Support\Facades\Log::info("LoadObserver: Processing Load #{$load->id}", [
            'status' => $load->status,
            'is_notified' => $load->is_notified
        ]);

        // 🚚 AREA 1: NEW LOAD AVAILABLE
        $statusMatch = strtolower($load->status) === 'available';
        if (!$load->is_notified && $statusMatch) {
            \Illuminate\Support\Facades\Log::info("LoadObserver: Triggering Notification for Load #{$load->id}");
            LocalNotification::show(
                '🚚 New Load Available!', 
                "From {$load->pickup_location} to {$load->drop_location} - \${$load->rate}",
                ['channelId' => 'loads']
            );
            
            // Set flag immediately to prevent duplicate if another sync process runs
            $load->updateQuietly(['is_notified' => true]);
        }
    }
}
