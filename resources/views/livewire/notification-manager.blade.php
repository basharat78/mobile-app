<?php

namespace App\Livewire;

use Livewire\Volt\Component;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Native\Mobile\Facades\PushNotifications;
use Native\Mobile\Facades\System;
use Native\Mobile\Facades\System as SystemFacade;
use App\Services\SyncService;

new class extends Component
{
    public $isFirstPulse = true;
    public $lastTokenSync = 0;

    /**
     * The Heartbeat: This runs globally on every page.
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

    public function requestPermission()
    {
        Log::info("PushToken: User requested permission manually.");
        try {
            // Standard enrollment triggers the native popup
            PushNotifications::enroll();
        } catch (\Exception $e) {
            Log::error("PushToken Manual Request Failed: " . $e->getMessage());
        }
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
                Log::info("PushToken: Token null, explicitly requesting permission...");
                try {
                    // Standard enrollment triggers the native popup
                    PushNotifications::enroll();
                } catch (\Exception $e) {
                    Log::error("PushToken Permission Request Failed: " . $e->getMessage());
                }
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

    @auth
        @if(!Auth::user()->fcm_token)
            <div class="fixed bottom-24 left-6 right-6 z-[9999] animate-bounce">
                <button wire:click="requestPermission" 
                        class="w-full bg-orange-600/90 backdrop-blur-lg text-white py-4 rounded-2xl font-black uppercase tracking-widest shadow-2xl border border-white/20 flex items-center justify-center gap-3 active:scale-95 transition-all">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor" class="w-5 h-5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9 3.75h.008v.008H12v-.008Z" />
                    </svg>
                    Fix Notifications (Allow Popup)
                </button>
            </div>
        @endif
    @endauth

    <!-- Diagnostic Link -->
    <a href="/debug/push" 
       style="position:fixed;bottom:80px;right:10px;z-index:9999;background:red;color:white;padding:6px 12px;border-radius:20px;font-size:11px;text-decoration:none;opacity:0.8;">
       🔍 DEBUG PUSH
    </a>
</div>
