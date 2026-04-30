/**
 * NativePHP Geolocation Plugin — JavaScript client
 *
 * For use with Inertia + Vue/React or any SPA frontend.
 * Import the functions you need and call them directly.
 *
 * @example
 *   import { GetCurrentPosition, CheckPermissions, RequestPermissions } from 'vendor-nativephp-geolocation';
 */

const BASE_URL = '/_native/api/call';

async function bridgeCall(method, params = {}) {
    const response = await fetch(BASE_URL, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ method, params }),
    });
    return response.json();
}

// ---------------------------------------------------------------------------
// GetCurrentPosition
// ---------------------------------------------------------------------------

/**
 * Retrieve the device's current position.
 *
 * Returns a chainable builder. Call .fineAccuracy(true) for GPS,
 * and .id('my-id') to tag the request so you can match it in the
 * LocationReceived event.
 *
 * The resolved value mirrors the LocationReceived event payload.
 *
 * @returns {{ fineAccuracy(bool): this, id(string): this, then: Function }}
 *
 * @example
 *   const loc = await GetCurrentPosition().fineAccuracy(true).id('home');
 */
export function GetCurrentPosition() {
    const options = { high_accuracy: false };
    const builder = {
        fineAccuracy(value = true) {
            options.high_accuracy = value;
            return builder;
        },
        id(value) {
            options.id = value;
            return builder;
        },
        then(resolve, reject) {
            return bridgeCall('Geolocation.GetCurrentPosition', options).then(resolve, reject);
        },
    };
    return builder;
}

// ---------------------------------------------------------------------------
// CheckPermissions
// ---------------------------------------------------------------------------

/**
 * Check the current location permission status.
 *
 * Resolves with { status: 'granted' | 'denied' | 'not_determined' }
 *
 * @returns {Promise<{ status: string }>}
 *
 * @example
 *   const { status } = await CheckPermissions();
 */
export async function CheckPermissions() {
    return bridgeCall('Geolocation.CheckPermissions');
}

// ---------------------------------------------------------------------------
// RequestPermissions
// ---------------------------------------------------------------------------

/**
 * Request location permissions from the user.
 *
 * The final result is delivered via the PermissionRequestResult event.
 * Possible values: 'granted' | 'denied' | 'permanently_denied'
 *
 * @returns {Promise<void>}
 *
 * @example
 *   await RequestPermissions();
 */
export async function RequestPermissions() {
    return bridgeCall('Geolocation.RequestPermissions');
}

// ---------------------------------------------------------------------------
// Event name constants (for use with On / Off helpers from #nativephp)
// ---------------------------------------------------------------------------

export const Events = {
    Geolocation: {
        LocationReceived: 'Vendor\\NativePHPGeolocation\\Events\\Geolocation\\LocationReceived',
        PermissionStatusReceived: 'Vendor\\NativePHPGeolocation\\Events\\Geolocation\\PermissionStatusReceived',
        PermissionRequestResult: 'Vendor\\NativePHPGeolocation\\Events\\Geolocation\\PermissionRequestResult',
    },
};
