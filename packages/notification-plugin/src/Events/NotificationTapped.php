<?php

namespace Vendor\LocalNotification\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Fired when the user taps a notification.
 */
class NotificationTapped
{
    use Dispatchable, SerializesModels;

    /**
     * @param  string  $id    The notification ID that was tapped
     * @param  array   $data  Custom payload data set when the notification was created
     */
    public function __construct(
        public readonly string $id,
        public readonly array $data = []
    ) {}
}
