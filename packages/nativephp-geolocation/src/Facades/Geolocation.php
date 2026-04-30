<?php

namespace Vendor\NativePHPGeolocation\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @method static mixed getCurrentPosition(bool $highAccuracy = false, ?string $id = null)
 * @method static mixed checkPermissions()
 * @method static mixed requestPermissions()
 *
 * @see \Vendor\NativePHPGeolocation\Geolocation
 */
class Geolocation extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return 'geolocation';
    }
}
