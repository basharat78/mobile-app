<?php

use Livewire\Volt\Component;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

new class extends Component
{
    public function getNotifications()
    {
        if (!Auth::check()) return;

        $user = Auth::user();
        
        // Use method call to ensure fresh data
        $notifications = $user->unreadNotifications()->get();

        if ($notifications->count() > 0) {
            Log::info("NotificationManager: Found {$notifications->count()} unread notifications for User ID: {$user->id}");
        }

        foreach ($notifications as $notification) {
            // Dispatch browser event for the toast
            // Using named arguments for reliable detail mapping in Alpine.js
            $this->dispatch('new-notification', 
                title: $notification->data['title'] ?? 'Notification',
                message: $notification->data['message'] ?? '',
                type: $notification->data['type'] ?? 'info'
            );

            // Mark as read immediately
            $notification->markAsRead();
        }
    }
};
?>

<div wire:poll.10s="getNotifications" class="hidden">
    <!-- Polling active -->
    <span class="sr-only">Checking for alerts...</span>
</div>
