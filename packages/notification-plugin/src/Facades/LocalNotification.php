<?php
namespace Vendor\LocalNotification\Facades;

use Illuminate\Support\Facades\Facade;

class LocalNotification extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return 'local-notification';
    }
}
