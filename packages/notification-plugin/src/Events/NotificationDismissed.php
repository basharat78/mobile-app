<?php

namespace Vendor\LocalNotification\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Fired when the user dismisses (swipes away) a notification.
 */
class NotificationDismissed
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly string $id,
        public readonly array $data = []
    ) {}
}
