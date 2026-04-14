/**
 * NativePHP Local Notification — JavaScript Bridge
 *
 * Drop-in JS library for Vue, React, Livewire, or vanilla JS.
 * Mirrors the PHP facade API 1:1.
 */

const BASE_URL = '/_native/api/call';

async function bridgeCall(method, params = {}) {
    const response = await fetch(BASE_URL, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ method, params }),
    });

    if (!response.ok) {
        throw new Error(`NativePHP bridge error: ${response.status}`);
    }

    return response.json();
}

// ---------------------------------------------------------------------------
// Public API
// ---------------------------------------------------------------------------

/**
 * Show a local notification.
 *
 * @param {string} title
 * @param {string} body
 * @param {object} options
 *   @param {string}  options.id          Unique notification ID (auto-generated if omitted)
 *   @param {string}  options.sound       'default' | 'none' | custom filename
 *   @param {number}  options.badge       Badge count
 *   @param {string}  options.icon        Android drawable resource name
 *   @param {string}  options.channelId   Android channel ID (default: 'default')
 *   @param {object}  options.data        Custom payload passed to NotificationTapped event
 *   @param {number}  options.scheduleAt  Unix timestamp for scheduled delivery
 *   @param {string}  options.group       Notification group key
 *   @param {boolean} options.ongoing     Android: persistent notification (default: false)
 *   @param {string}  options.priority    'high' | 'default' | 'low'
 * @returns {Promise<{id: string}>}
 */
export async function showNotification(title, body, options = {}) {
    return bridgeCall('LocalNotification.Show', { title, body, ...options });
}

/**
 * Request notification permission from the OS.
 * @returns {Promise<{granted: boolean, status: string}>}
 */
export async function requestNotificationPermission() {
    return bridgeCall('LocalNotification.RequestPermission');
}

/**
 * Check current notification permission status.
 * @returns {Promise<{granted: boolean, status: string}>}
 */
export async function checkNotificationPermission() {
    return bridgeCall('LocalNotification.CheckPermission');
}

/**
 * Cancel a specific notification.
 * @param {string} id
 */
export async function cancelNotification(id) {
    return bridgeCall('LocalNotification.Cancel', { id });
}

/**
 * Cancel all notifications.
 */
export async function cancelAllNotifications() {
    return bridgeCall('LocalNotification.CancelAll');
}

/**
 * Set the app icon badge count. Pass 0 to clear.
 * @param {number} count
 */
export async function setNotificationBadge(count) {
    return bridgeCall('LocalNotification.SetBadge', { count });
}

/**
 * Create a notification channel (Android only, no-op on iOS).
 * @param {string} id
 * @param {string} name
 * @param {object} options
 *   @param {string}  options.importance  'high' | 'default' | 'low' | 'none'
 *   @param {string}  options.description
 *   @param {boolean} options.sound
 *   @param {boolean} options.vibration
 *   @param {boolean} options.lights
 */
export async function createNotificationChannel(id, name, options = {}) {
    return bridgeCall('LocalNotification.CreateChannel', { id, name, ...options });
}

// ---------------------------------------------------------------------------
// Default export for convenience
// ---------------------------------------------------------------------------
export default {
    show:          showNotification,
    requestPermission: requestNotificationPermission,
    checkPermission:   checkNotificationPermission,
    cancel:        cancelNotification,
    cancelAll:     cancelAllNotifications,
    setBadge:      setNotificationBadge,
    createChannel: createNotificationChannel,
};
