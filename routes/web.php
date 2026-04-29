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

    // 4. Check current user
    $user = Auth::user();
    $results['user_logged_in'] = $user ? true : false;
    $results['user_id'] = $user?->id;
    $results['current_fcm_token'] = $user?->fcm_token;

    // 5. If we got a token, try saving it
    if (function_exists('nativephp_call')) {
        $raw = nativephp_call('PushNotification.GetToken', '{}');
        if ($raw) {
            $decoded = json_decode($raw, true);
            $token = $decoded['token'] ?? null;
            if ($token && $token !== '' && $user) {
                $user->update(['fcm_token' => $token]);
                $results['SAVED_TOKEN'] = $token;
            }
        }
    }

    return response()->json($results, 200, [], JSON_PRETTY_PRINT);
});
