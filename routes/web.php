<?php

use Illuminate\Support\Facades\Route;
use Livewire\Volt\Volt;
use Illuminate\Support\Facades\Auth;

Route::get('/', function () {
    return redirect()->route('login');
});

Volt::route('/login', 'auth.login')->name('login');
Volt::route('/signup', 'auth.signup')->name('signup');

Route::middleware('auth')->group(function () {
    // Carrier Routes
    Route::middleware('role:carrier')->group(function () {
        Route::get('/dashboard', function () {
            return view('dashboard');
        })->name('dashboard');

        // Volt::route('/document-upload', 'auth.document-upload')->name('document-upload');
        // Volt::route('/preferences', 'auth.carrier-preferences')->name('preferences');
        // Volt::route('/my-requests', 'carrier.my-requests')->name('my-requests');
        // Volt::route('/notifications', 'carrier.notifications')->name('notifications');
        // Volt::route('/loads', 'loads')->name('loads');
    });

    // Shared Auth Routes
    // Volt::route('/profile', 'auth.profile')->name('profile');

    Route::post('/logout', function () {
        Auth::logout();
        request()->session()->invalidate();
        request()->session()->regenerateToken();
        return redirect('/');
    })->name('logout');

    // Auth Recovery
    // Volt::route('/forgot-password', 'auth.forgot-password')->name('password.request');
    // Volt::route('/reset-password/{token}', 'auth.reset-password')->name('password.reset');

    // Dispatcher Routes
    Route::middleware('role:dispatcher')->prefix('dispatcher')->name('dispatcher.')->group(function () {
        Volt::route('/dashboard', 'dispatcher.dashboard')->name('dashboard');
        // Volt::route('/carriers', 'dispatcher.carriers')->name('carriers');
        // Volt::route('/loads', 'dispatcher.loads')->name('loads');
    });
});
