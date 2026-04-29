<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Artisan;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // 1. Register 'layouts' namespace to fix "No hint path defined for [layouts]"
        // This ensures <x-layouts.app> or layouts::app resolves to resources/views/components/layouts
        \Illuminate\Support\Facades\View::addNamespace('layouts', resource_path('views/components/layouts'));

        // 2. Auto-create & Migrate SQLite database
        if (config('database.default') === 'sqlite') {
            $dbPath = config('database.connections.sqlite.database');
            
            // \Illuminate\Support\Facades\Log::info('Checking SQLite database', ['path' => $dbPath]);

            if ($dbPath && $dbPath !== ':memory:') {
                try {
                    // Create directory and file if it doesn't exist
                    $dir = dirname($dbPath);
                    if (!is_dir($dir)) {
                        mkdir($dir, 0755, true);
                    }
                    if (!file_exists($dbPath)) {
                        touch($dbPath);
                        \Illuminate\Support\Facades\Log::info('SQLite file created');
                    }

                    // v93: High-Performance Migration Check
                    // Only check schema if the .migrated flag is missing.
                    // This prevents 2-minute boot hangs on slow mobile storage.
                    $migrationFlag = $dbPath . '.migrated';
                    if (!file_exists($migrationFlag)) {
                        if (!\Illuminate\Support\Facades\Schema::hasTable('users')) {
                            \Illuminate\Support\Facades\Log::info('Database table(s) missing. Running migrations...');
                            Artisan::call('migrate', ['--force' => true]);
                            \Illuminate\Support\Facades\Log::info('Migrations executed successfully');
                        }
                        touch($migrationFlag);
                    }
                } catch (\Exception $e) {
                    \Illuminate\Support\Facades\Log::error('SQLite Setup Error', ['error' => $e->getMessage()]);
                }
            }
        }

        // 3. Setup Local Notification Channels (Android 8+)
        if (class_exists(\Vendor\LocalNotification\Facades\LocalNotification::class)) {
            try {
                \Vendor\LocalNotification\Facades\LocalNotification::createChannel(
                    id: 'loads',
                    name: 'New Loads',
                    importance: 'high',
                    description: 'Alerts for new available freight',
                    sound: true,
                    vibration: true,
                    lights: true
                );

                \Vendor\LocalNotification\Facades\LocalNotification::createChannel(
                    id: 'status_updates',
                    name: 'Status Updates',
                    importance: 'high',
                    description: 'Alerts for bid and account status changes',
                    sound: true,
                    vibration: true,
                    lights: true
                );
            } catch (\Exception $e) {
                \Illuminate\Support\Facades\Log::warning('Notification Channel setup failed', ['error' => $e->getMessage()]);
            }
        }
        // Observers removed in v71 in favor of direct service calls for higher reliability
    }
}
