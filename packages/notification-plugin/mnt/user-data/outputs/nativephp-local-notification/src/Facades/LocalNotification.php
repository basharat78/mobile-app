<?php

namespace Vendor\LocalNotification\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @method static string|null show(string $title, string $body, array $options = [])
 * @method static array requestPermission()
 * @method static array checkPermission()
 * @method static void cancel(string $id)
 * @method static void cancelAll()
 * @method static void setBadge(int $count)
 * @method static void createChannel(string $id, string $name, string $importance = 'high', string $description = '', bool $sound = true, bool $vibration = true, bool $lights = true)
 *
 * @see \Vendor\LocalNotification\LocalNotification
 */
class LocalNotification extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return 'local-notification';
    }
}
