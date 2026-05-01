<?php

namespace App\Services;

use Vendor\NativePHPGeolocation\Facades\Geolocation;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;
use Native\Mobile\Facades\System;

class GpsService
{
    /**
     * Get the current position and push it to the remote server.
     */
    public static function syncLocation($user)
    {
        Log::info("GpsService: Starting syncLocation FORCED for User #{$user->id}");

        try {
            Log::info("GpsService: Checking location permissions...");
            
            if (!class_exists(\Vendor\NativePHPGeolocation\Facades\Geolocation::class)) {
                Log::error("GpsService: CRITICAL - Geolocation facade NOT FOUND!");
                return;
            }

            // 1. Check Permissions
            $permission = Geolocation::checkPermissions();
            $status = is_string($permission) ? $permission : ($permission->status ?? 'denied');

            if (!str_contains($status, 'granted')) {
                Log::warning("GpsService: Location permission is {$status}. Requesting...");
                Geolocation::requestPermissions();
                return ['success' => false, 'error' => "Permission: {$status}"];
            }

            // 2. Get Position
            $position = Geolocation::getCurrentPosition(true);

            if (!$position) {
                Log::warning("GpsService: Failed to acquire position (null).");
                return ['success' => false, 'error' => 'Failed to acquire position (null)'];
            }

            if (isset($position->status) && $position->status === 'error') {
                Log::warning("GpsService: Failed to acquire position: " . ($position->message ?? 'Unknown error'));
                return ['success' => false, 'error' => $position->message ?? 'Bridge error'];
            }

            Log::info("GpsService: Position acquired: {$position->latitude}, {$position->longitude}");

            // --- 3. SAVE LOCALLY (v126) ---
            $carrier = $user->carrier;
            
            if (!$carrier) {
                Log::info("GpsService: Local carrier record missing. Creating one...");
                $carrier = \App\Models\Carrier::create([
                    'user_id' => $user->id,
                    'status' => 'approved' // Default status for debug/testing
                ]);
            }

            if ($carrier) {
                $carrier->update([
                    'last_lat' => $position->latitude,
                    'last_lng' => $position->longitude,
                    'last_location_update' => now(),
                ]);
                Log::info("GpsService: Local database updated.");
            }

            // --- 4. PUSH TO REMOTE SERVER ---
            $baseUrl = env('REMOTE_API_URL') ?: 'https://truck-app.morphoworks.com';
            $response = Http::timeout(5)->post("{$baseUrl}/api/carrier/update-location", [
                'email' => $user->email,
                'latitude' => $position->latitude,
                'longitude' => $position->longitude,
                'accuracy' => $position->accuracy ?? 0,
                'provider' => $position->provider ?? 'unknown',
                'timestamp' => $position->timestamp ?? time(),
            ]);

            if ($response->successful()) {
                Log::info("GpsService: Location successfully pushed to remote.");
                return ['success' => true, 'remote_status' => $response->status(), 'remote_body' => $response->json()];
            } else {
                Log::warning("GpsService: Remote push failed with status: " . $response->status());
                return ['success' => false, 'remote_status' => $response->status(), 'remote_body' => $response->json()];
            }

        } catch (\Exception $e) {
            Log::error("GpsService Error: " . $e->getMessage());
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
}
