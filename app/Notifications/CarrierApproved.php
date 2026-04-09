<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class CarrierApproved extends Notification
{
    use Queueable;

    public function __construct()
    {
        //
    }

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'title' => 'Account Approved!',
            'message' => 'Congratulations, your carrier account has been approved. You can now start requesting loads.',
            'type' => 'success',
        ];
    }
}
