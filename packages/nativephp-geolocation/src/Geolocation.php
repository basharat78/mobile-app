<?php

namespace Vendor\NativePHPGeolocation;

class Geolocation
{
    /**
     * Get the device's current position.
     *
     * @param  bool  $highAccuracy  Use GPS (true) or network location (false). GPS is slower but more accurate.
     * @param  string|null  $id  Optional identifier for tracking which request triggered the event.
     */
    public function getCurrentPosition(bool $highAccuracy = false, ?string $id = null): mixed
    {
        if (function_exists('nativephp_call')) {
            $params = ['high_accuracy' => $highAccuracy];

            if ($id !== null) {
                $params['id'] = $id;
            }

            $result = nativephp_call('Geolocation.GetCurrentPosition', json_encode($params));

            return json_decode($result)?->data;
        }

        return null;
    }

    /**
     * Check the current location permission status.
     *
     * Returns 'granted', 'denied', or 'not_determined'.
     */
    public function checkPermissions(): mixed
    {
        if (function_exists('nativephp_call')) {
            $result = nativephp_call('Geolocation.CheckPermissions', json_encode([]));

            return json_decode($result)?->data;
        }

        return null;
    }

    /**
     * Request location permissions from the user.
     *
     * The result is delivered via the PermissionRequestResult event.
     * Special value 'permanently_denied' means the user has blocked permissions in system settings.
     */
    public function requestPermissions(): mixed
    {
        if (function_exists('nativephp_call')) {
            $result = nativephp_call('Geolocation.RequestPermissions', json_encode([]));

            return json_decode($result)?->data;
        }

        return null;
    }
}
