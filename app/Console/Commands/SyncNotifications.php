<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\User;
use App\Models\Load;
use App\Models\LoadRequest;
use App\Models\CarrierDocument;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Vendor\LocalNotification\Facades\LocalNotification;

class SyncNotifications extends Command
{
    protected $signature = 'app:sync-notifications {email}';
    protected $description = 'Perform a background sync of loads, bids, and statuses for a specific user email.';

    public function handle()
    {
        $email = $this->argument('email');
        $user = User::where('email', $email)->first();

        if (!$user || !$user->carrier) {
            Log::error("SyncNotifications: User or carrier not found for email: {$email}");
            return Command::FAILURE;
        }

        $carrier = $user->carrier;

        try {
            // --- AREA 1: LOADS & BIDS ---
            $loadsUrl = (env('REMOTE_API_URL') ?: 'https://mobile.morphoworks.com') . '/api/carrier/loads/' . $email;
            $loadsResponse = Http::timeout(20)->get($loadsUrl);
            
            if ($loadsResponse->successful()) {
                $remoteLoads = $loadsResponse->json()['loads'] ?? [];
                
                foreach ($remoteLoads as $l) {
                    $load = Load::updateOrCreate(
                        ['id' => $l['id']],
                        [
                            'dispatcher_id' => $l['dispatcher_id'],
                            'carrier_id' => $carrier->id, 
                            'pickup_location' => $l['pickup_location'],
                            'pickup_time' => $l['pickup_time'],
                            'drop_location' => $l['drop_location'],
                            'drop_off_time' => $l['drop_off_time'],
                            'miles' => $l['miles'],
                            'rate' => $l['rate'],
                            'equipment_type' => $l['equipment_type'],
                            'status' => $l['status'],
                            'notes' => $l['notes'],
                        ]
                    );

                    // 1.A: Notification for LOAD CREATED
                    if (!$load->is_notified && $load->status === 'available') {
                        Log::info("SyncNotifications: Notifying New Load Available #{$load->id}");
                        LocalNotification::show(
                            '🚚 New Load Available!', 
                            "From {$load->pickup_location} to {$load->drop_location} - ${$load->rate}",
                            ['channelId' => 'loads']
                        );
                        $load->update(['is_notified' => true]);
                    }

                    // 1.B: Notification for BID APPROVED / REJECTED
                    if (isset($l['requests']) && count($l['requests']) > 0) {
                        $remoteReq = $l['requests'][0];
                        $localReq = LoadRequest::updateOrCreate(
                            ['load_id' => $load->id, 'carrier_id' => $carrier->id],
                            ['status' => $remoteReq['status']]
                        );

                        // Only notify if status is approved/rejected AND we haven't notified for this specific one yet
                        $isFinalStatus = in_array($localReq->status, ['approved', 'rejected']);
                        if ($isFinalStatus && !$localReq->is_notified) {
                            $statusText = strtoupper($localReq->status);
                            Log::info("SyncNotifications: Notifying Bid {$statusText} for Load #{$load->id}");
                            LocalNotification::show(
                                "🎯 Bid {$statusText}", 
                                "Your bid for the load from {$load->pickup_location} has been {$localReq->status}.",
                                ['channelId' => 'status_updates']
                            );
                            $localReq->update(['is_notified' => true]);
                        }
                    }
                }
            }

            // --- AREA 2 & 3: ACCOUNT & DOCUMENTS ---
            $statusUrl = (env('REMOTE_API_URL') ?: 'https://mobile.morphoworks.com') . '/api/carrier/status/' . $email;
            $statusResponse = Http::timeout(15)->get($statusUrl);

            if ($statusResponse->successful()) {
                $statusData = $statusResponse->json();

                // 2.A: Notification for ACCOUNT APPROVED / REJECTED
                if (isset($statusData['status'])) {
                    $newStatus = $statusData['status'];
                    $statusHasChanged = ($newStatus !== $carrier->status);
                    
                    // If status changed to a final state, OR if it's already in a final state and we haven't notified yet
                    $isFinalStatus = in_array($newStatus, ['approved', 'rejected']);
                    
                    if ($isFinalStatus && ($statusHasChanged || !$carrier->is_notified)) {
                        $statusText = strtoupper($newStatus);
                        Log::info("SyncNotifications: Notifying Account {$statusText}");
                        LocalNotification::show(
                            "🏢 Account {$statusText}", 
                            "Your carrier account has been {$newStatus} by the dispatch team.",
                            ['channelId' => 'status_updates', 'badge' => 1]
                        );
                        $carrier->update([
                            'status' => $newStatus,
                            'is_notified' => true
                        ]);
                    } else if ($statusHasChanged) {
                        // Just update status if it's not final, but don't notify
                        $carrier->update(['status' => $newStatus]);
                    }
                }

                // 3.A: Notification for DOCUMENTS APPROVED / REJECTED
                if (!empty($statusData['documents'])) {
                    foreach ($statusData['documents'] as $remoteDoc) {
                        $localDoc = CarrierDocument::updateOrCreate(
                            ['carrier_id' => $carrier->id, 'type' => $remoteDoc['type']],
                            ['status' => $remoteDoc['status']]
                        );

                        $isFinalStatus = in_array($localDoc->status, ['approved', 'rejected']);
                        if ($isFinalStatus && !$localDoc->is_notified) {
                            $docName = ucfirst(str_replace('_', ' ', $remoteDoc['type']));
                            $statusText = strtoupper($localDoc->status);
                            Log::info("SyncNotifications: Notifying Document {$docName} {$statusText}");
                            LocalNotification::show(
                                "📄 Document {$statusText}", 
                                "Your {$docName} has been {$localDoc->status}.",
                                ['channelId' => 'status_updates', 'badge' => 1]
                            );
                            $localDoc->update(['is_notified' => true]);
                        }
                    }
                }
            }

            return Command::SUCCESS;

        } catch (\Exception $e) {
            Log::error("SyncNotifications Exception: " . $e->getMessage());
            return Command::FAILURE;
        }
    }
}
