<?php

namespace App\Services;

use App\Models\Load;
use App\Models\LoadRequest;
use App\Models\Carrier;
use App\Models\CarrierDocument;
use Vendor\LocalNotification\Facades\LocalNotification;
use Illuminate\Support\Facades\Log;

class NotificationService
{
    /**
     * Notify if a new load is available.
     */
    public static function notifyNewLoad(Load $load)
    {
        $status = strtolower($load->status ?? '');
        Log::info("NotificationService: Evaluating Load #{$load->id} (Status: {$status}, Notified: {$load->is_notified})");

        if (!$load->is_notified && $status === 'available') {
            Log::info("NotificationService: Triggering 'New Load' Alert for #{$load->id}");
            
            LocalNotification::show(
                '🚚 New Load Available!',
                "From {$load->pickup_location} to {$load->drop_location} - \${$load->rate}",
                ['channelId' => 'loads', 'data' => ['load_id' => $load->id]]
            );

            $load->updateQuietly(['is_notified' => true]);
        }
    }

    /**
     * Notify if a bid status has changed (Approved/Rejected).
     */
    public static function notifyBidStatus(LoadRequest $request)
    {
        $status = strtolower($request->status ?? '');
        $isFinal = in_array($status, ['approved', 'rejected']);
        
        Log::info("NotificationService: Evaluating Bid #{$request->id} (Status: {$status}, Notified: {$request->is_notified})");

        if (!$request->is_notified && $isFinal) {
            Log::info("NotificationService: Triggering 'Bid Status' Alert for #{$request->id}");
            
            $statusText = strtoupper($status);
            $load = $request->loadJob;

            LocalNotification::show(
                "💼 Bid {$statusText}",
                "Your request for load #{$request->load_id} ({$load->pickup_location}) was {$status}.",
                ['channelId' => 'status_updates', 'data' => ['load_id' => $request->load_id]]
            );

            $request->updateQuietly(['is_notified' => true]);
        }
    }

    /**
     * Notify if account status has changed.
     */
    public static function notifyAccountStatus(Carrier $carrier)
    {
        $status = strtolower($carrier->status ?? '');
        $isFinal = in_array($status, ['approved', 'rejected']);

        Log::info("NotificationService: Evaluating Account #{$carrier->id} (Status: {$status}, Notified: {$carrier->is_notified})");

        if (!$carrier->is_notified && $isFinal) {
            Log::info("NotificationService: Triggering 'Account Status' Alert for #{$carrier->id}");
            
            $statusText = strtoupper($status);
            LocalNotification::show(
                "🏢 Account {$statusText}",
                "Your carrier account has been {$status}.",
                ['channelId' => 'status_updates']
            );

            $carrier->updateQuietly(['is_notified' => true]);
        }
    }

    /**
     * Notify if a document has been approved/rejected.
     */
    public static function notifyDocumentStatus(CarrierDocument $document)
    {
        $status = strtolower($document->status ?? '');
        $isFinal = in_array($status, ['approved', 'rejected']);

        Log::info("NotificationService: Evaluating Doc #{$document->id} (Status: {$status}, Notified: {$document->is_notified})");

        if (!$document->is_notified && $isFinal) {
            Log::info("NotificationService: Triggering 'Doc Status' Alert for #{$document->id}");
            
            $docName = ucfirst(str_replace('_', ' ', $document->type));
            $statusText = strtoupper($status);

            LocalNotification::show(
                "📄 Document {$statusText}",
                "Your {$docName} has been {$status}.",
                ['channelId' => 'status_updates']
            );

            $document->updateQuietly(['is_notified' => true]);
        }
    }
}
