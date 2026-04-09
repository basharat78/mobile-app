<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class CarrierRegistered extends Notification
{
    use Queueable;

    protected $carrierName;

    public function __construct($carrierName)
    {
        $this->carrierName = $carrierName;
    }

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'title' => 'New Carrier Registered',
            'message' => "{$this->carrierName} has joined the platform.",
            'type' => 'info',
        ];
    }
}
