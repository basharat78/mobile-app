<?php

namespace App\Observers;

use App\Models\CarrierDocument;
use Vendor\LocalNotification\Facades\LocalNotification;

class CarrierDocumentObserver
{
    /**
     * Handle the CarrierDocument "saved" event.
     */
    public function saved(CarrierDocument $document): void
    {
        \Illuminate\Support\Facades\Log::info("CarrierDocumentObserver: Processing Doc #{$document->id} ({$document->type})", [
            'status' => $document->status,
            'is_notified' => $document->is_notified
        ]);

        // 📄 AREA 4: DOCUMENT APPROVED / REJECTED
        $status = strtolower($document->status);
        $isFinalStatus = in_array($status, ['approved', 'rejected']);

        if (!$document->is_notified && $isFinalStatus) {
            \Illuminate\Support\Facades\Log::info("CarrierDocumentObserver: Triggering Notification for Doc #{$document->id} ({$status})");
            $docName = ucfirst(str_replace('_', ' ', $document->type));
            $statusText = strtoupper($status);
            
            LocalNotification::show(
                "📄 Document {$statusText}", 
                "Your {$docName} has been {$document->status}.",
                ['channelId' => 'status_updates', 'badge' => 1]
            );

            // Update flag immediately
            $document->updateQuietly(['is_notified' => true]);
        }
    }
}
