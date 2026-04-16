<?php

namespace App\Services;

use App\Models\User;
use App\Models\Load;
use App\Models\LoadRequest;
use App\Models\CarrierDocument;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use App\Services\NotificationService;

class SyncService
{
    /**
     * The master synchronization method. Performs a full cloud sync for a given user.
     * This is used by Foreground Heartbeat, Manual Sync, and Background Workers.
     * 
     * @return array Sync statistics
     */
    public static function performGlobalSync(User $user)
    {
        $carrier = $user->carrier;
        if (!$carrier) return ['status' => 'error', 'message' => 'No carrier profile found.'];

        $stats = [
            'loads_found' => 0,
            'status_synced' => false,
            'docs_synced' => 0,
            'last_sync' => now()->format('H:i:s')
        ];

        try {
            $baseUrl = env('REMOTE_API_URL') ?: 'https://mobile.morphoworks.com';
            $email = $user->email;

            // --- 1. SYNC LOADS & BIDS ---
            $loadsUrl = "{$baseUrl}/api/carrier/loads/{$email}";
            $loadsResponse = Http::timeout(15)->get($loadsUrl);
            
            if ($loadsResponse->successful()) {
                $remoteLoads = $loadsResponse->json()['loads'] ?? [];
                $stats['loads_found'] = count($remoteLoads);
                
                $syncedIds = [];
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
                            'deadhead' => $l['deadhead'] ?? 0,
                            'total_miles' => $l['total_miles'] ?? $l['miles'],
                            'rpm' => $l['rpm'] ?? 0,
                            'equipment_type' => $l['equipment_type'],
                            'weight' => $l['weight'] ?? 0,
                            'broker_name' => $l['broker_name'] ?? 'Direct',
                            'notes' => $l['notes'] ?? '',
                            'status' => $l['status'],
                        ]
                    );
                    
                    // Trigger Alert evaluation
                    NotificationService::notifyNewLoad($load);

                    // Sync Bid status if present
                    if (isset($l['requests']) && count($l['requests']) > 0) {
                        $remoteReq = $l['requests'][0];
                        $localReq = LoadRequest::updateOrCreate(
                            ['load_id' => $load->id, 'carrier_id' => $carrier->id],
                            ['status' => $remoteReq['status']]
                        );
                        // Trigger Alert evaluation
                        NotificationService::notifyBidStatus($localReq);
                    }

                    $syncedIds[] = $load->id;
                }

                // Ghost Load Cleanup (Optional/Conservative)
                if (count($syncedIds) > 0) {
                    // We don't delete locally anymore to avoid UI flickers, 
                    // but we could mark them as hidden if needed.
                }
            }

            // --- 2. SYNC ACCOUNT & DOCUMENT STATUS ---
            $statusUrl = "{$baseUrl}/api/carrier/status/{$email}";
            $statusResponse = Http::timeout(10)->get($statusUrl);

            if ($statusResponse->successful()) {
                $data = $statusResponse->json();

                // Account Status
                if (isset($data['status'])) {
                    $carrier->update(['status' => $data['status']]);
                    NotificationService::notifyAccountStatus($carrier);
                    $stats['status_synced'] = true;
                }

                // Document Status
                if (!empty($data['documents'])) {
                    foreach ($data['documents'] as $remoteDoc) {
                        $doc = CarrierDocument::updateOrCreate(
                            ['carrier_id' => $carrier->id, 'type' => $remoteDoc['type']],
                            ['status' => $remoteDoc['status']]
                        );
                        NotificationService::notifyDocumentStatus($doc);
                        $stats['docs_synced']++;
                    }
                }
            }

            Log::info("GlobalSync: Success for {$email}", $stats);
            return ['status' => 'success', 'data' => $stats];

        } catch (\Exception $e) {
            Log::error("GlobalSync: Failed for {$email}. Error: " . $e->getMessage());
            return ['status' => 'error', 'message' => $e->getMessage()];
        }
    }
}
