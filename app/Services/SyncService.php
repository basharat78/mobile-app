<?php

namespace App\Services;

use App\Models\User;
use App\Models\Load;
use App\Models\LoadRequest;
use App\Models\CarrierDocument;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use App\Services\NotificationService;
use App\Services\GpsService;

class SyncService
{
    public static function performGlobalSync(User $user, $silent = false)
    {
        $carrier = $user->carrier;
        if (!$carrier) return ['status' => 'error', 'message' => 'No carrier profile found.'];
        
        $stats = [
            'loads_found' => 0,
            'status_synced' => false,
            'docs_synced' => 0,
            'last_sync' => now()->format('H:i:s')
        ];

        $context = app()->runningInConsole() ? 'BACKGROUND' : 'FOREGROUND';
        $baseUrl = env('REMOTE_API_URL') ?: 'https://truck-app.morphoworks.com';
        $email = $user->email;

        Log::debug("SyncService: Starting {$context} pulse for {$email}");
        
        // --- 0. SYNC GPS LOCATION (v125: New Feature) ---
        GpsService::syncLocation($user);

        // --- 1. SYNC FCM TOKEN (v115: Prioritized & Decoupled) ---
        if ($user->fcm_token) {
            try {
                Log::info("SyncService: Pushing FCM token for {$email}");
                Http::timeout(7)->post("{$baseUrl}/api/carrier/update-token", [
                    'email' => $email,
                    'fcm_token' => $user->fcm_token
                ]);
            } catch (\Exception $e) {
                Log::warning("SyncService: Token push failed: " . $e->getMessage());
            }
        }

        // --- 2. SYNC LOADS & BIDS ---
        try {
            $loadsUrl = "{$baseUrl}/api/carrier/loads/{$email}";
            $loadsResponse = Http::timeout(15)->get($loadsUrl);
            
            if ($loadsResponse->successful()) {
                $remoteLoads = $loadsResponse->json()['loads'] ?? [];
                $stats['loads_found'] = count($remoteLoads);
                
                foreach ($remoteLoads as $l) {
                    $existingLoad = Load::find($l['id']);
                    
                    $load = Load::updateOrCreate(
                        ['id' => $l['id']],
                        [
                            'dispatcher_id' => $l['dispatcher_id'],
                            'dispatcher_phone' => $l['dispatcher_phone'] ?? null,
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
                            'status' => $l['status']
                        ]
                    );
                    
                    NotificationService::notifyNewLoad($load, $silent);

                    if (isset($l['requests']) && count($l['requests']) > 0) {
                        $remoteReq = $l['requests'][0];
                        $localReq = LoadRequest::updateOrCreate(
                            ['load_id' => $load->id, 'carrier_id' => $carrier->id],
                            ['status' => $remoteReq['status']]
                        );
                        NotificationService::notifyBidStatus($localReq, $silent);
                    }
                }
            }
        } catch (\Exception $e) {
            Log::error("SyncService: Loads sync failed: " . $e->getMessage());
        }

        // --- 3. SYNC ACCOUNT & DOCUMENT STATUS ---
        try {
            $statusUrl = "{$baseUrl}/api/carrier/status/{$email}";
            $statusResponse = Http::timeout(10)->get($statusUrl);

            if ($statusResponse->successful()) {
                $data = $statusResponse->json();

                if (isset($data['status'])) {
                    if ($carrier->status !== $data['status']) {
                        $carrier->update(['status' => $data['status'], 'is_notified' => false]);
                    }
                    NotificationService::notifyAccountStatus($carrier, $silent);
                    $stats['status_synced'] = true;
                }

                if (!empty($data['documents'])) {
                    foreach ($data['documents'] as $remoteDoc) {
                        $doc = CarrierDocument::updateOrCreate(
                            ['carrier_id' => $carrier->id, 'type' => $remoteDoc['type']],
                            ['status' => $remoteDoc['status']]
                        );
                        NotificationService::notifyDocumentStatus($doc, $silent);
                        $stats['docs_synced']++;
                    }
                }
            }
        } catch (\Exception $e) {
            Log::error("SyncService: Status sync failed: " . $e->getMessage());
        }

        return ['status' => 'success', 'data' => $stats];
    }

    public function syncRemoteAccountStatus()
    {
        $user = auth()->user();
        return $user ? self::performGlobalSync($user) : ['status' => 'error'];
    }
}
