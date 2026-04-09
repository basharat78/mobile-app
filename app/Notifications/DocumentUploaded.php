<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class DocumentUploaded extends Notification
{
    use Queueable;

    protected $carrierName;
    protected $docType;

    public function __construct($carrierName, $docType)
    {
        $this->carrierName = $carrierName;
        $this->docType = $docType;
    }

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'title' => 'New Document Uploaded',
            'message' => "{$this->carrierName} uploaded a " . str_replace('_', ' ', $this->docType) . ".",
            'type' => 'document',
        ];
    }
}
