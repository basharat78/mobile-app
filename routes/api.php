<?php

use App\Http\Controllers\Api\RegisterController;
use App\Http\Controllers\Api\DocumentController;
use App\Http\Controllers\Api\HealthController;
use Illuminate\Support\Facades\Route;

// Health check — no auth required
Route::get('/health', [HealthController::class, 'check']);

// Registration — no auth required (user doesn't have a token yet)
Route::post('/register', [RegisterController::class, 'store']);

// Document upload — no auth for now (will add Sanctum later)
// ... existing routes ...
Route::post('/documents/upload', [DocumentController::class, 'upload']);

// Carrier Management APIs (v27)
Route::group(['prefix' => 'carrier'], function() {
    Route::get('/status/{userId}', [\App\Http\Controllers\Api\CarrierApiController::class, 'getStatus']);
    Route::post('/preferences', [\App\Http\Controllers\Api\CarrierApiController::class, 'syncPreferences']);
    Route::post('/lookup', [\App\Http\Controllers\Api\CarrierApiController::class, 'lookup']);
    Route::get('/loads/{email}', [\App\Http\Controllers\Api\LoadApiController::class, 'getAvailable'])->where('email', '.*');
    Route::post('/loads/request', [\App\Http\Controllers\Api\LoadApiController::class, 'postRequest']);
    Route::post('/authenticate', [\App\Http\Controllers\Api\CarrierApiController::class, 'authenticate']);
});

// Native Pulse Diagnostic ...
Route::get('/_native/api/diagnostic', function () {
    return [
        'runtime' => function_exists('nativephp_call') ? 'OK' : 'INCOMPATIBLE',
        'functions' => [
            'nativephp_call' => function_exists('nativephp_call'),
            'nativephp_can' => function_exists('nativephp_can'),
        ],
        'version' => 'v23'
    ];
});
