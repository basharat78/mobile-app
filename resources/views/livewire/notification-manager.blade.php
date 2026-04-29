<?php

namespace App\Livewire;

use Livewire\Volt\Component;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Native\Mobile\Facades\PushNotifications;
use Native\Mobile\Facades\System;
use App\Services\SyncService;

new class extends Component
{
    public $isFirstPulse = true;
    public $lastTokenSync = 0;

    /**
     * The Heartbeat: This runs globally on every page.
     * It ensures the local database is always fresh and triggers alerts.
     */
    public function performGlobalHeartbeat()
    {
        if (!Auth::check()) {
            return;
        }

        $user = Auth::user();
        
        // Pulse sync
        try {
            Log::info("Heartbeat: Pulsing sync for User #{$user->id}");
            SyncService::performGlobalSync($user, $this->isFirstPulse);
            $this->checkLocalNotifications($user);
        } catch (\Exception $e) {
            Log::error("Heartbeat Sync Error: " . $e->getMessage());
        }

        // Token management (every 2 minutes or on first pulse)
        if (System::isMobile() && (time() - $this->lastTokenSync > 120 || $this->isFirstPulse)) {
            $this->syncPushToken($user);
            $this->lastTokenSync = time();
        }

        $this->isFirstPulse = false;
    }

    protected function syncPushToken($user)
    {
        Log::info("PushToken: Checking token for User #{$user->id}");
        try {
            $rawToken = PushNotifications::getToken();
            
            if ($rawToken) {
                $token = $rawToken;
                if (is_string($rawToken) && str_starts_with($rawToken, '{')) {
                    $decoded = json_decode($rawToken, true);
                    $token = $decoded['token'] ?? $rawToken;
                }

                if ($user->fcm_token !== $token) {
                    $user->fcm_token = $token;
                    $user->save();
                    Log::info("PushToken: Saved locally for User #{$user->id}");
                    
                    // Force remote update immediately
                    SyncService::performGlobalSync($user);
                }
            } else {
                Log::info("PushToken: Token null, enrolling...");
                PushNotifications::enroll();
            }
        } catch (\Exception $e) {
            Log::error("PushToken Error: " . $e->getMessage());
        }
    }

    protected function checkLocalNotifications($user)
    {
        $notifications = $user->unreadNotifications()->get();

        foreach ($notifications as $notification) {
            if (!$this->isFirstPulse) {
                $this->dispatch('new-notification', 
                    title: $notification->data['title'] ?? 'Notification',
                    message: $notification->data['message'] ?? '',
                    type: $notification->data['type'] ?? 'info'
                );
            }
            $notification->markAsRead();
        }
    }
};
?>

<div>
    <div wire:poll.30s="performGlobalHeartbeat" class="hidden">
        <!-- Watchtower: Active -->
    </div>

    <!-- Diagnostic Link -->
    <a href="/debug/push" 
       style="position:fixed;bottom:80px;right:10px;z-index:9999;background:red;color:white;padding:6px 12px;border-radius:20px;font-size:11px;text-decoration:none;opacity:0.8;">
       🔍 DEBUG PUSH
    </a>
</div>
