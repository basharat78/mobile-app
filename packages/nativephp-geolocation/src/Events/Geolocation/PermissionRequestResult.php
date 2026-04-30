<?php

namespace Vendor\NativePHPGeolocation\Events\Geolocation;

use Illuminate\Foundation\Events\Dispatchable;

class PermissionRequestResult
{
    use Dispatchable;

    public function __construct(
        /**
         * Result of the permission request.
         *
         * Possible values:
         *   'granted'            — user granted the permission
         *   'denied'             — user denied the permission
         *   'permanently_denied' — user has permanently blocked permissions (must go to system settings)
         */
        public readonly string $status,
    ) {}
}
