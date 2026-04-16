<?php

namespace App\Observers;

use App\Models\LoadRequest;
use Vendor\LocalNotification\Facades\LocalNotification;

class LoadRequestObserver
{
    /**
     * Handle the LoadRequest "saved" event.
     */
    public function saved(LoadRequest $request): void
    {
        \Illuminate\Support\Facades\Log::info("LoadRequestObserver: Processing Bid #{$request->id}", [
            'status' => $request->status,
            'is_notified' => $request->is_notified
        ]);

        // 🎯 AREA 2: BID APPROVED / REJECTED
        $status = strtolower($request->status);
        $isFinalStatus = in_array($status, ['approved', 'rejected']);

        if (!$request->is_notified && $isFinalStatus) {
            \Illuminate\Support\Facades\Log::info("LoadRequestObserver: Triggering Notification for Bid #{$request->id} ({$status})");
            $statusText = strtoupper($status);
            $load = $request->loadJob;
            
            LocalNotification::show(
                "🎯 Bid {$statusText}", 
                "Your bid for the load from " . ($load ? $load->pickup_location : "the marketplace") . " has been {$request->status}.",
                ['channelId' => 'status_updates']
            );

            // Update flag immediately to prevent repeat alerts
            $request->updateQuietly(['is_notified' => true]);
        }
    }
}
