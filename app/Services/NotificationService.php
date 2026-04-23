<?php

namespace App\Services;

use App\Models\Load;
use App\Models\LoadRequest;
use App\Models\Carrier;
use App\Models\CarrierDocument;
use Vendor\LocalNotification\Facades\LocalNotification;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

class NotificationService
{
    /**
     * Notify if a new load is available.
     */
    public static function notifyNewLoad(Load $load, $silent = false)
    {
        $status = strtolower($load->status ?? '');
        Log::info("NotificationService: Evaluating Load #{$load->id} (Status: {$status}, Notified: {$load->is_notified}, Silent: {$silent})");

        if (!$load->is_notified && $status === 'available') {
            $lockKey = "notified_load_{$load->id}_{$status}";
            if (Cache::has($lockKey)) return;

            if (!$silent) {
                Log::info("NotificationService: Triggering 'New Load' Alert for #{$load->id}");
                LocalNotification::show(
                    '🚚 New Load Available!',
                    "From {$load->pickup_location} to {$load->drop_location} - \${$load->rate}",
                    [
                        'channelId' => 'loads', 
                        'data' => [
                            'type' => 'load_details',
                            'load_id' => $load->id,
                            'url' => '/loads' // Or specific load URL if implemented
                        ]
                    ]
                );
            }

            Cache::put($lockKey, true, now()->addMinutes(5));
            $load->updateQuietly(['is_notified' => true]);
        }
    }

    /**
     * Notify if a bid status has changed (Approved/Rejected).
     */
    public static function notifyBidStatus(LoadRequest $request, $silent = false)
    {
        $status = strtolower($request->status ?? '');
        $isFinal = in_array($status, ['approved', 'rejected']);
        
        Log::info("NotificationService: Evaluating Bid #{$request->id} (Status: {$status}, Notified: {$request->is_notified}, Silent: {$silent})");

        if (!$request->is_notified && $isFinal) {
            $lockKey = "notified_bid_{$request->id}_{$status}";
            if (Cache::has($lockKey)) return;

            if (!$silent) {
                Log::info("NotificationService: Triggering 'Bid Status' Alert for #{$request->id}");
                $statusText = strtoupper($status);
                $load = $request->loadJob;

                LocalNotification::show(
                    "💼 Bid {$statusText}",
                    "Your request for load #{$request->load_id} ({$load->pickup_location}) was {$status}.",
                    [
                        'channelId' => 'status_updates', 
                        'data' => [
                            'type' => 'bid_details',
                            'load_id' => $request->load_id,
                            'url' => '/my-requests'
                        ]
                    ]
                );
            }

            Cache::put($lockKey, true, now()->addMinutes(5));
            $request->updateQuietly(['is_notified' => true]);
        }
    }

    /**
     * Notify if account status has changed.
     */
    public static function notifyAccountStatus(Carrier $carrier, $silent = false)
    {
        $status = strtolower($carrier->status ?? '');
        $isFinal = in_array($status, ['approved', 'rejected']);

        Log::info("NotificationService: Evaluating Account #{$carrier->id} (Status: {$status}, Notified: {$carrier->is_notified}, Silent: {$silent})");

        if (!$carrier->is_notified && $isFinal) {
            $lockKey = "notified_account_{$carrier->id}_{$status}";
            if (Cache::has($lockKey)) return;

            if (!$silent) {
                Log::info("NotificationService: Triggering 'Account Status' Alert for #{$carrier->id}");
                $statusText = strtoupper($status);
                LocalNotification::show(
                    "🏢 Account {$statusText}",
                    "Your carrier account has been {$status}.",
                    [
                        'channelId' => 'status_updates',
                        'data' => [
                            'type' => 'account_status',
                            'url' => '/dashboard'
                        ]
                    ]
                );
            }

            Cache::put($lockKey, true, now()->addMinutes(5));
            $carrier->updateQuietly(['is_notified' => true]);
        }
    }

    /**
     * Notify if a document has been approved/rejected.
     */
    public static function notifyDocumentStatus(CarrierDocument $document, $silent = false)
    {
        $status = strtolower($document->status ?? '');
        $isFinal = in_array($status, ['approved', 'rejected']);

        Log::info("NotificationService: Evaluating Doc #{$document->id} (Status: {$status}, Notified: {$document->is_notified}, Silent: {$silent})");

        if (!$document->is_notified && $isFinal) {
            $lockKey = "notified_doc_{$document->id}_{$status}";
            if (Cache::has($lockKey)) return;

            if (!$silent) {
                Log::info("NotificationService: Triggering 'Doc Status' Alert for #{$document->id}");
                $docName = ucfirst(str_replace('_', ' ', $document->type));
                $statusText = strtoupper($status);

                LocalNotification::show(
                    "📄 Document {$statusText}",
                    "Your {$docName} has been {$status}.",
                    [
                        'channelId' => 'status_updates',
                        'data' => [
                            'type' => 'document_status',
                            'url' => '/fleet'
                        ]
                    ]
                );
            }

            Cache::put($lockKey, true, now()->addMinutes(5));
            $document->updateQuietly(['is_notified' => true]);
        }
    }
}
