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
        if (!System::isMobile()) {
            return;
        }

        try {
            Log::info("GpsService: Checking location permissions for User #{$user->id}");
            
            if (!class_exists(\Vendor\NativePHPGeolocation\Facades\Geolocation::class)) {
                Log::warning("GpsService: Geolocation plugin class not found. Is the package installed?");
                return;
            }

            // 1. Check Permissions
            $permission = Geolocation::checkPermissions();
            $status = is_string($permission) ? $permission : ($permission->status ?? 'denied');

            if ($status !== 'granted') {
                Log::warning("GpsService: Location permission is {$status}. Requesting...");
                Geolocation::requestPermissions();
                return;
            }

            // 2. Get Position (High Accuracy for better tracking)
            Log::info("GpsService: Fetching current position...");
            $position = Geolocation::getCurrentPosition(true);

            if (!$position || !isset($position->latitude)) {
                Log::warning("GpsService: Failed to get position (timeout or error).");
                return;
            }

            Log::info("GpsService: Position acquired: {$position->latitude}, {$position->longitude}");

            // 3. Push to Remote Server
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
            } else {
                Log::warning("GpsService: Remote push failed with status: " . $response->status());
            }

        } catch (\Exception $e) {
            Log::error("GpsService Error: " . $e->getMessage());
        }
    }
}
