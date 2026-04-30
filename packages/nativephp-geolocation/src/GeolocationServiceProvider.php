<?php

namespace Vendor\NativePHPGeolocation;

use Illuminate\Support\ServiceProvider;

class GeolocationServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind('geolocation', fn () => new Geolocation());
    }

    public function boot(): void
    {
        //
    }
}
