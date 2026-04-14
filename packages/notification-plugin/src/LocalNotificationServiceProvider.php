<?php

namespace Vendor\LocalNotification;

use Illuminate\Support\ServiceProvider;

class LocalNotificationServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton('local-notification', function () {
            return new LocalNotification();
        });
    }

    public function boot(): void
    {
        //
    }
}
