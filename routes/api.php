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
Route::post('/documents/upload', [DocumentController::class, 'upload']);

// Native Pulse Diagnostic — check if nativephp_call exists in the PHP binary
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
