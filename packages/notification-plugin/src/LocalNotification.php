<?php

namespace Vendor\LocalNotification;

class LocalNotification
{
    /**
     * Display a local notification.
     *
     * @param  string       $title       Notification title
     * @param  string       $body        Notification message body
     * @param  array        $options     Additional options:
     *   - id          (string)  Unique notification ID (default: auto-generated)
     *   - sound       (string)  Sound file name or 'default' | 'none'
     *   - badge       (int)     Badge count to set on the app icon
     *   - icon        (string)  Android small icon resource name (e.g. 'ic_notification')
     *   - channelId   (string)  Android channel ID (default: 'default')
     *   - data        (array)   Custom key-value payload passed to NotificationTapped event
     *   - scheduleAt  (int)     Unix timestamp to schedule notification (null = immediate)
     *   - group       (string)  Notification group key (for grouping multiple notifications)
     *   - ongoing     (bool)    Android: cannot be dismissed by user (default: false)
     *   - priority    (string)  'high' | 'default' | 'low' (default: 'high')
     * @return string|null      Notification ID
     */
    public function show(string $title, string $body, array $options = []): ?string
    {
        if (! function_exists('nativephp_call')) {
            return null;
        }

        $params = array_merge([
            'id'         => $options['id'] ?? uniqid('notif_', true),
            'title'      => $title,
            'body'       => $body,
            'sound'      => 'default',
            'badge'      => null,
            'icon'       => 'ic_notification',
            'channelId'  => 'default',
            'data'       => [],
            'scheduleAt' => null,
            'group'      => null,
            'ongoing'    => false,
            'priority'   => 'high',
        ], $options);

        $result = nativephp_call('LocalNotification.Show', json_encode($params));
        $decoded = json_decode($result, true);

        return $decoded['data']['id'] ?? $params['id'];
    }

    /**
     * Request notification permission from the user.
     * On Android 13+ this shows the system dialog.
     * On iOS this shows the UNUserNotificationCenter dialog.
     *
     * @return array  ['granted' => bool, 'status' => string]
     */
    public function requestPermission(): array
    {
        if (! function_exists('nativephp_call')) {
            return ['granted' => false, 'status' => 'unavailable'];
        }

        $result = nativephp_call('LocalNotification.RequestPermission', json_encode([]));
        return json_decode($result, true)['data'] ?? ['granted' => false, 'status' => 'unknown'];
    }

    /**
     * Check current notification permission status.
     *
     * @return array  ['granted' => bool, 'status' => 'granted'|'denied'|'not_determined']
     */
    public function checkPermission(): array
    {
        if (! function_exists('nativephp_call')) {
            return ['granted' => false, 'status' => 'unavailable'];
        }

        $result = nativephp_call('LocalNotification.CheckPermission', json_encode([]));
        return json_decode($result, true)['data'] ?? ['granted' => false, 'status' => 'unknown'];
    }

    /**
     * Cancel a specific notification by its ID.
     */
    public function cancel(string $id): void
    {
        if (! function_exists('nativephp_call')) {
            return;
        }

        nativephp_call('LocalNotification.Cancel', json_encode(['id' => $id]));
    }

    /**
     * Cancel all pending and delivered notifications.
     */
    public function cancelAll(): void
    {
        if (! function_exists('nativephp_call')) {
            return;
        }

        nativephp_call('LocalNotification.CancelAll', json_encode([]));
    }

    /**
     * Set the app badge count.
     * Pass 0 to clear the badge.
     */
    public function setBadge(int $count): void
    {
        if (! function_exists('nativephp_call')) {
            return;
        }

        nativephp_call('LocalNotification.SetBadge', json_encode(['count' => $count]));
    }

    /**
     * Create a notification channel (Android 8+ / API 26+).
     * This is a no-op on iOS.
     *
     * @param  string  $id          Channel ID (used in show() channelId option)
     * @param  string  $name        User-visible channel name
     * @param  string  $importance  'high' | 'default' | 'low' | 'none'
     * @param  string  $description Optional user-visible description
     * @param  bool    $sound       Whether notifications in this channel play sound
     * @param  bool    $vibration   Whether notifications in this channel vibrate
     * @param  bool    $lights      Whether notifications in this channel show lights
     */
    public function createChannel(
        string $id,
        string $name,
        string $importance = 'high',
        string $description = '',
        bool $sound = true,
        bool $vibration = true,
        bool $lights = true
    ): void {
        if (! function_exists('nativephp_call')) {
            return;
        }

        nativephp_call('LocalNotification.CreateChannel', json_encode([
            'id'          => $id,
            'name'        => $name,
            'importance'  => $importance,
            'description' => $description,
            'sound'       => $sound,
            'vibration'   => $vibration,
            'lights'      => $lights,
        ]));
    }
}
