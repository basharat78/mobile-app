<?php

namespace Vendor\NativePHPGeolocation\Events\Geolocation;

use Illuminate\Foundation\Events\Dispatchable;

class LocationReceived
{
    use Dispatchable;

    public function __construct(
        /** Whether location was successfully retrieved */
        public readonly bool $success,

        /** Latitude coordinate (when successful) */
        public readonly ?float $latitude = null,

        /** Longitude coordinate (when successful) */
        public readonly ?float $longitude = null,

        /** Accuracy in meters (when successful) */
        public readonly ?float $accuracy = null,

        /** Unix timestamp of the location fix */
        public readonly ?int $timestamp = null,

        /** Location provider used: 'gps', 'network', 'fused', etc. */
        public readonly ?string $provider = null,

        /** Optional ID passed when calling getCurrentPosition() */
        public readonly ?string $id = null,

        /** Error message (when unsuccessful) */
        public readonly ?string $error = null,
    ) {}
}
