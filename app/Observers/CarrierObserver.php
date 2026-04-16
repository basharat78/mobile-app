<?php

namespace App\Observers;

use App\Models\Carrier;
use Vendor\LocalNotification\Facades\LocalNotification;

class CarrierObserver
{
    /**
     * Handle the Carrier "saved" event.
     */
    public function saved(Carrier $carrier): void
    {
        \Illuminate\Support\Facades\Log::info("CarrierObserver: Processing Account #{$carrier->id}", [
            'status' => $carrier->status,
            'is_notified' => $carrier->is_notified
        ]);

        // 🏢 AREA 3: ACCOUNT APPROVED / REJECTED
        $status = strtolower($carrier->status);
        $isFinalStatus = in_array($status, ['approved', 'rejected']);

        if (!$carrier->is_notified && $isFinalStatus) {
            \Illuminate\Support\Facades\Log::info("CarrierObserver: Triggering Notification for Account #{$carrier->id} ({$status})");
            $statusText = strtoupper($status);
            
            LocalNotification::show(
                "🏢 Account {$statusText}", 
                "Your carrier account has been {$carrier->status} by the dispatch team.",
                ['channelId' => 'status_updates', 'badge' => 1]
            );

            // Update flag immediately
            $carrier->updateQuietly(['is_notified' => true]);
        }
    }
}
