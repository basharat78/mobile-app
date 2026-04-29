<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class NativeServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        \Illuminate\Support\Facades\Log::info("NativeServiceProvider: Booting. Registering Push Token Listener.");
        
        \Illuminate\Support\Facades\Event::listen(
            \Native\Mobile\Events\PushNotification\TokenGenerated::class,
            function ($event) {
                \Illuminate\Support\Facades\Log::info("PushToken Event Received: " . $event->token);
                
                if ($user = \Illuminate\Support\Facades\Auth::user()) {
                    $user->update(['fcm_token' => $event->token]);
                    \Illuminate\Support\Facades\Log::info("PushToken: Saved to Local User #{$user->id}");
                    
                    // FORCE SYNC TO REMOTE SERVER IMMEDIATELY
                    try {
                        app(\App\Services\SyncService::class)->syncRemoteAccountStatus();
                        \Illuminate\Support\Facades\Log::info("PushToken: Sync to Remote Server Triggered.");
                    } catch (\Exception $e) {
                        \Illuminate\Support\Facades\Log::error("PushToken Sync Failed: " . $e->getMessage());
                    }
                } else {
                    \Illuminate\Support\Facades\Log::warning("PushToken Received but no user is logged in.");
                }
            }
        );
    }

    /**
     * The NativePHP plugins to enable.
     *
     * Only plugins listed here will be compiled into your native builds.
     * This is a security measure to prevent transitive dependencies from
     * automatically registering plugins without your explicit consent.
     *
     * @return array<int, class-string<\Illuminate\Support\ServiceProvider>>
     */
    public function plugins(): array
    {
        return [
            \Native\Mobile\Providers\CameraServiceProvider::class,
            \Native\Mobile\Providers\DeviceServiceProvider::class,
            \Native\Mobile\Providers\SystemServiceProvider::class,
            \Vendor\LocalNotification\LocalNotificationServiceProvider::class,
        

        ];
    }
}