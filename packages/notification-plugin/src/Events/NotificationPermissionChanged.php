<?php

namespace Vendor\LocalNotification\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Fired when notification permission status changes.
 */
class NotificationPermissionChanged
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly bool $granted,
        public readonly string $status   // 'granted' | 'denied' | 'not_determined'
    ) {}
}
