<?php

use Illuminate\Support\Facades\Route;
use Livewire\Volt\Volt;
use Illuminate\Support\Facades\Auth;
use App\Http\Controllers\DocumentDownloadController;

// Document download route (no symlink needed for shared hosting)
Route::get('/docs/{path}', [DocumentDownloadController::class, 'show'])
    ->where('path', '.*')
    ->name('document.download');

Route::get('/', function () {
    return redirect()->route('login');
});

Route::middleware('guest')->group(function () {
    Volt::route('/login', 'auth.login')->name('login');
    Volt::route('/signup', 'auth.signup')->name('signup');
});

Route::middleware('auth')->group(function () {
    // Carrier Routes
    Route::middleware('role:carrier')->group(function () {
        Volt::route('/dashboard', 'dashboard')->name('dashboard');

        Volt::route('/document-upload', 'auth.document-upload')->name('document-upload');
        Volt::route('/preferences', 'auth.carrier-preferences')->name('preferences');
        Volt::route('/fleet', 'carrier.fleet-menu')->name('fleet');
        Volt::route('/my-requests', 'carrier.my-requests')->name('my-requests');

        Volt::route('/loads', 'loads')->name('loads');

     
    });

    // Shared Auth Routes
     Volt::route('/profile', 'auth.profile')->name('profile');

    Route::post('/logout', function () {
        Auth::logout();
        request()->session()->invalidate();
        request()->session()->regenerateToken();
        return redirect('/');
    })->name('logout');

  
    // Dispatcher Routes
    Route::middleware('role:dispatcher')->prefix('dispatcher')->name('dispatcher.')->group(function () {
        Volt::route('/dashboard', 'dispatcher.dashboard')->name('dashboard');
        Volt::route('/carriers', 'dispatcher.carriers')->name('carriers');
        Volt::route('/loads', 'dispatcher.loads')->name('loads');

    });
});

// DEBUG: Push notification diagnostic page
Route::get('/debug/push', function () {
    $results = [];
    $user = Auth::user();

    // 1. Check if nativephp_call exists
    $results['nativephp_call_exists'] = function_exists('nativephp_call');

    // 2. Check if nativephp_can exists and test bridge functions
    $results['nativephp_can_exists'] = function_exists('nativephp_can');

    if (function_exists('nativephp_can')) {
        $results['can_GetToken'] = nativephp_can('PushNotification.GetToken');
        $results['can_RequestPermission'] = nativephp_can('PushNotification.RequestPermission');
        $results['can_CheckPermission'] = nativephp_can('PushNotification.CheckPermission');
    }

    // 3. Try to get the token
    if (function_exists('nativephp_call')) {
        $raw = nativephp_call('PushNotification.GetToken', '{}');
        $results['getToken_raw'] = $raw;
        if ($raw) {
            $decoded = json_decode($raw, true);
            $results['getToken_decoded'] = $decoded;
        }
    }

    // 4. GEOLOCATION DIAGNOSTICS (v125)
    if (function_exists('nativephp_can')) {
        $results['can_GetCurrentPosition'] = nativephp_can('Geolocation.GetCurrentPosition');
        $results['can_CheckPermissions'] = nativephp_can('Geolocation.CheckPermissions');
        $results['can_RequestPermissions'] = nativephp_can('Geolocation.RequestPermissions');
    }

    if (function_exists('nativephp_call')) {
        $results['checkPermissions_raw'] = nativephp_call('Geolocation.CheckPermissions', '{}');
    }

    if (function_exists('nativephp_call')) {
        try {
            $rawPos = nativephp_call('Geolocation.GetCurrentPosition', json_encode(['high_accuracy' => true]));
            $results['geolocation_raw'] = $rawPos;
            
            // If denied, trigger the request popup automatically for the user
            $decoded = json_decode($rawPos, true);
            if (isset($decoded['error']) && str_contains($decoded['error'], 'permission')) {
                nativephp_call('Geolocation.RequestPermissions', '{}');
                $results['ACTION_TAKEN'] = "Permission request triggered automatically.";
            }

            if ($rawPos) {
                $decoded = json_decode($rawPos);
                if (isset($decoded->success) && $decoded->success && $user) {
                    $results['REMOTE_SYNC_RESULT'] = \App\Services\GpsService::syncLocation($user);
                    $results['ACTION_TAKEN_SYNC'] = "GpsService::syncLocation triggered.";
                    $user->refresh();
                    $user->load('carrier');
                }
                $results['geolocation_decoded'] = json_decode($rawPos, true);
            }
        } catch (\Exception $e) {
            $results['geolocation_error'] = $e->getMessage();
        }
    }

    // 5. DATABASE DIAGNOSTICS (v126)
    try {
        $columns = \Illuminate\Support\Facades\Schema::getColumnListing('carriers');
        $results['db_carriers_columns'] = $columns;
        $results['has_gps_columns'] = in_array('last_lat', $columns);
        
        if (!in_array('last_lat', $columns) && request()->has('migrate')) {
            \Illuminate\Support\Facades\Artisan::call('migrate', ['--force' => true]);
            $results['MIGRATION_STATUS'] = "Migration command executed.";
        }
    } catch (\Exception $e) {
        $results['db_error'] = $e->getMessage();
    }

    // 6. Check current user
    $results['user_logged_in'] = $user ? true : false;
    $results['user_id'] = $user?->id;
    $results['user_email'] = $user?->email;
    $results['current_fcm_token'] = $user?->fcm_token;
    if ($user && $user->carrier) {
        $results['carrier_last_lat'] = $user->carrier->last_lat;
        $results['carrier_last_lng'] = $user->carrier->last_lng;
    }

    return response()->json($results, 200, [], JSON_PRETTY_PRINT);
});
