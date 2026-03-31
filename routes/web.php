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

    });
});
