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

class SyncService
{
    /**
     * The master synchronization method. Performs a full cloud sync for a given user.
     * This is used by Foreground Heartbeat, Manual Sync, and Background Workers.
     * 
     * @return array Sync statistics
     */
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
        Log::debug("SyncService: Starting {$context} pulse for {$user->email}");
        Storage::disk('local')->append('logs/sync_pulse.log', "[" . now()->toDateTimeString() . "] [{$context}] Heartbeat started for {$user->email}");

        // --- 0. REGISTRATION RECOVERY (v78) ---
        // If we don't have a remote_id, check if we have pending signup data to retry
        if (!$carrier->remote_id && $carrier->pending_registration_data) {
            self::recoverPendingRegistration($user);
        }

        // --- 0b. DOCUMENT SYNC RECOVERY (v106) ---
        self::syncPendingDocuments($user);


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
                    $existingLoad = Load::find($l['id']);
                    if ($existingLoad && $existingLoad->status !== $l['status']) {
                        $l['is_notified'] = false; // Reset if status changed
                    }

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
                            'status' => $l['status'],
                            'is_notified' => $l['is_notified'] ?? ($existingLoad ? $existingLoad->is_notified : false)
                        ]
                    );
                    
                    // Trigger Alert evaluation
                    NotificationService::notifyNewLoad($load, $silent);

                    // Sync Bid status if present
                    if (isset($l['requests']) && count($l['requests']) > 0) {
                        $remoteReq = $l['requests'][0];
                        $localReq = LoadRequest::where('load_id', $load->id)->where('carrier_id', $carrier->id)->first();
                        if ($localReq && $localReq->status !== $remoteReq['status']) {
                            $localReq->update(['status' => $remoteReq['status'], 'is_notified' => false]);
                        } elseif (!$localReq) {
                            $localReq = LoadRequest::create([
                                'load_id' => $load->id,
                                'carrier_id' => $carrier->id,
                                'status' => $remoteReq['status']
                            ]);
                        }
                        // Trigger Alert evaluation
                        NotificationService::notifyBidStatus($localReq, $silent);
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
                    if ($carrier->status !== $data['status']) {
                        $carrier->update(['status' => $data['status'], 'is_notified' => false]);
                    }
                    NotificationService::notifyAccountStatus($carrier, $silent);
                    $stats['status_synced'] = true;
                }

                // Document Status
                if (!empty($data['documents'])) {
                    foreach ($data['documents'] as $remoteDoc) {
                        $doc = CarrierDocument::where('carrier_id', $carrier->id)->where('type', $remoteDoc['type'])->first();
                        if ($doc && $doc->status !== $remoteDoc['status']) {
                            $doc->update(['status' => $remoteDoc['status'], 'is_notified' => false]);
                        } elseif (!$doc) {
                            $doc = CarrierDocument::create([
                                'carrier_id' => $carrier->id,
                                'type' => $remoteDoc['type'],
                                'status' => $remoteDoc['status'],
                                'is_notified' => false
                            ]);
                        }
                        NotificationService::notifyDocumentStatus($doc, $silent);
                        $stats['docs_synced']++;
                    }
                }
            }

            Log::info("GlobalSync: Success for {$email}", $stats);
            Storage::disk('local')->append('logs/sync_pulse.log', "[" . now()->toDateTimeString() . "] [{$context}] Sync SUCCESS. Loads: {$stats['loads_found']}, Docs: {$stats['docs_synced']}");
            return ['status' => 'success', 'data' => $stats];

        } catch (\Exception $e) {
            Log::error("GlobalSync: Failed for {$email}. Error: " . $e->getMessage());
            Storage::disk('local')->append('logs/sync_pulse.log', "[" . now()->toDateTimeString() . "] [{$context}] Sync FAILED: " . $e->getMessage());
            return ['status' => 'error', 'message' => $e->getMessage()];
        }
    }

    /**
     * Retries failed document uploads.
     */
    protected static function syncPendingDocuments(User $user)
    {
        $carrier = $user->carrier;
        // Find documents that might have failed to sync (status pending but no remote confirmation)
        // For simplicity, we'll look for local docs that haven't been updated by remote in a while
        // or we could add an 'is_synced' column.
        $pendingDocs = CarrierDocument::where('carrier_id', $carrier->id)
            ->where('status', 'pending')
            ->where('created_at', '<', now()->subMinutes(5)) // Only retry after 5 mins to avoid race conditions
            ->get();

        if ($pendingDocs->isEmpty()) return;

        Log::info("SyncService: Attempting Document Sync Recovery for {$user->email} (" . $pendingDocs->count() . " docs)");

        foreach ($pendingDocs as $doc) {
            try {
                $baseUrl = env('REMOTE_API_URL') ?: 'https://mobile.morphoworks.com';
                $storagePath = storage_path('app/public/' . $doc->file_path);
                
                if (!file_exists($storagePath)) continue;

                $response = Http::timeout(30)
                    ->attach('file', file_get_contents($storagePath), basename($storagePath))
                    ->post("{$baseUrl}/api/documents/upload", [
                        'carrier_id' => $carrier->remote_id ?? $carrier->id,
                        'type' => $doc->type,
                        'user_name' => $user->name,
                        'dispatcher_id' => $carrier->dispatcher_id,
                        'is_retry' => true
                    ]);

                if ($response->successful()) {
                    Log::info("SyncService: Document Recovery SUCCESS for {$doc->type}");
                }
            } catch (\Exception $e) {
                Log::error("SyncService: Document Recovery ERROR for {$doc->type}: " . $e->getMessage());
            }
        }
    }

    /**
     * Attempts to register a carrier who signed up offline.
     */
    protected static function recoverPendingRegistration($user)
    {
        $carrier = $user->carrier;
        $data = json_decode($carrier->pending_registration_data, true);
        if (!$data) return;

        Log::info("SyncService: Attempting Registration Recovery for {$user->email}");
        Storage::disk('local')->append('logs/sync_pulse.log', "[" . now()->toDateTimeString() . "] [RECOVERY] Retrying registration for {$user->email}");

        try {
            $baseUrl = env('REMOTE_API_URL') ?: 'https://mobile.morphoworks.com';
            $response = Http::timeout(15)->post("{$baseUrl}/api/register", $data);

            if ($response->successful()) {
                $remoteData = $response->json();
                if (isset($remoteData['carrier_id'])) {
                    $carrier->update([
                        'remote_id' => $remoteData['carrier_id'],
                        'pending_registration_data' => null // SUCCESS: Clear the queue
                    ]);
                    Log::info("SyncService: Registration Recovery SUCCESS for {$user->email}");
                    Storage::disk('local')->append('logs/sync_pulse.log', "[" . now()->toDateTimeString() . "] [RECOVERY] SUCCESS! Remote ID: " . $remoteData['carrier_id']);
                }
            } else {
                Log::warning("SyncService: Registration Recovery FAILED for {$user->email}", ['status' => $response->status()]);
            }
        } catch (\Exception $e) {
            Log::error("SyncService: Registration Recovery ERROR for {$user->email}: " . $e->getMessage());
        }
    }
}
