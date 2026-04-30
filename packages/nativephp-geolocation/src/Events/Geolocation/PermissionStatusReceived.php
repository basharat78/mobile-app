<?php

namespace Vendor\NativePHPGeolocation\Events\Geolocation;

use Illuminate\Foundation\Events\Dispatchable;

class PermissionStatusReceived
{
    use Dispatchable;

    public function __construct(
        /**
         * Current permission status.
         *
         * Possible values:
         *   'granted'        — permission has been granted
         *   'denied'         — permission has been denied
         *   'not_determined' — permission has not been requested yet
         */
        public readonly string $status,
    ) {}
}
