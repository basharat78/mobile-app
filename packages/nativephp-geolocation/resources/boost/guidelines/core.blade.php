## NativePHP Geolocation Plugin

Provides GPS / network location access and permission handling for NativePHP Mobile apps.

### Facade (PHP / Livewire)

```php
use Vendor\NativePHPGeolocation\Facades\Geolocation;

// Get position with network accuracy (fast, less accurate)
Geolocation::getCurrentPosition();

// Get position with GPS accuracy (slower, more accurate)
Geolocation::getCurrentPosition(highAccuracy: true);

// Tag a request so you can match it in LocationReceived
Geolocation::getCurrentPosition(highAccuracy: true, id: 'home');

// Check current permission status
Geolocation::checkPermissions();  // returns array with 'status' key

// Request permissions (result arrives via PermissionRequestResult event)
Geolocation::requestPermissions();
```

### Events (Livewire)

```php
use Native\Mobile\Attributes\OnNative;
use Vendor\NativePHPGeolocation\Events\Geolocation\LocationReceived;
use Vendor\NativePHPGeolocation\Events\Geolocation\PermissionStatusReceived;
use Vendor\NativePHPGeolocation\Events\Geolocation\PermissionRequestResult;

#[OnNative(LocationReceived::class)]
public function handleLocation(
    bool $success,
    ?float $latitude = null,
    ?float $longitude = null,
    ?float $accuracy = null,
    ?int $timestamp = null,
    ?string $provider = null,
    ?string $id = null,
    ?string $error = null
): void {
    if ($success) {
        // $latitude, $longitude, $accuracy available
    }
}

#[OnNative(PermissionStatusReceived::class)]
public function handlePermissionStatus(string $status): void
{
    // 'granted' | 'denied' | 'not_determined'
}

#[OnNative(PermissionRequestResult::class)]
public function handlePermissionResult(string $status): void
{
    // 'granted' | 'denied' | 'permanently_denied'
}
```

### JavaScript (Vue / React / Inertia)

```js
import { GetCurrentPosition, CheckPermissions, RequestPermissions, Events } from 'vendor-nativephp-geolocation';
import { On, Off } from '#nativephp';

// Get location (chainable builder)
const result = await GetCurrentPosition().fineAccuracy(true).id('my-request');

// Check / request permissions
const { status } = await CheckPermissions();
await RequestPermissions();

// Listen for location events
On(Events.Geolocation.LocationReceived, (payload) => {
    if (payload.success) console.log(payload.latitude, payload.longitude);
});
```

### Permission Status Values
- `'granted'` — permission is granted
- `'denied'` — permission is denied
- `'not_determined'` — permission not yet requested
- `'permanently_denied'` — user permanently blocked (iOS / Android system settings required)

### Common Patterns
1. Always call `checkPermissions()` first; if `not_determined`, call `requestPermissions()`.
2. Use `highAccuracy: false` (default) for quick lookups; `true` for precise navigation.
3. Pass an `id` to distinguish concurrent location requests.
4. On `permanently_denied`, guide the user to system settings — you cannot re-prompt programmatically.
