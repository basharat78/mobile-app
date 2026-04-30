# NativePHP Geolocation Plugin

GPS location and permission handling for NativePHP Mobile v3.

---

## Installation

```bash
composer require vendor/nativephp-geolocation
php artisan native:plugin:register vendor/nativephp-geolocation
```

---

## Usage (PHP / Livewire)

```php
use Vendor\NativePHPGeolocation\Facades\Geolocation;

// Network location — faster, less accurate (default)
Geolocation::getCurrentPosition();

// GPS — slower, more accurate
Geolocation::getCurrentPosition(highAccuracy: true);

// Tagged request — match by $id in LocationReceived
Geolocation::getCurrentPosition(highAccuracy: true, id: 'delivery');

// Check permission status
Geolocation::checkPermissions();

// Request permissions
Geolocation::requestPermissions();
```

### Livewire component example

```php
use Livewire\Component;
use Native\Mobile\Attributes\OnNative;
use Vendor\NativePHPGeolocation\Facades\Geolocation;
use Vendor\NativePHPGeolocation\Events\Geolocation\LocationReceived;
use Vendor\NativePHPGeolocation\Events\Geolocation\PermissionRequestResult;

class LocationTracker extends Component
{
    public ?float $latitude = null;
    public ?float $longitude = null;
    public ?string $permissionStatus = null;
    public ?string $error = null;

    public function getLocation(): void
    {
        Geolocation::getCurrentPosition(highAccuracy: true);
    }

    public function checkAndRequestPermissions(): void
    {
        $result = Geolocation::checkPermissions();
        $this->permissionStatus = $result['status'] ?? null;

        if ($this->permissionStatus === 'not_determined') {
            Geolocation::requestPermissions();
        }
    }

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
            $this->latitude = $latitude;
            $this->longitude = $longitude;
        } else {
            $this->error = $error;
        }
    }

    #[OnNative(PermissionRequestResult::class)]
    public function handlePermissionResult(string $status): void
    {
        $this->permissionStatus = $status;
    }

    public function render()
    {
        return view('livewire.location-tracker');
    }
}
```

---

## Usage (JavaScript — Vue / React / Inertia)

```js
import { GetCurrentPosition, CheckPermissions, RequestPermissions, Events } from 'vendor-nativephp-geolocation';
import { On, Off } from '#nativephp';
```

### Vue component example

```vue
<script setup>
import { ref, onMounted, onUnmounted } from 'vue';
import { GetCurrentPosition, CheckPermissions, RequestPermissions, Events } from 'vendor-nativephp-geolocation';
import { On, Off } from '#nativephp';

const latitude = ref(null);
const longitude = ref(null);
const error = ref('');

const handleLocation = (payload) => {
    if (payload.success) {
        latitude.value = payload.latitude;
        longitude.value = payload.longitude;
    } else {
        error.value = payload.error;
    }
};

onMounted(() => {
    On(Events.Geolocation.LocationReceived, handleLocation);
});

onUnmounted(() => {
    Off(Events.Geolocation.LocationReceived, handleLocation);
});

async function getLocation() {
    const { status } = await CheckPermissions();
    if (status === 'not_determined') {
        await RequestPermissions();
    }
    await GetCurrentPosition().fineAccuracy(true).id('my-location');
}
</script>

<template>
    <button @click="getLocation">Get Location</button>
    <p v-if="latitude">{{ latitude }}, {{ longitude }}</p>
    <p v-if="error">{{ error }}</p>
</template>
```

---

## Events

### `LocationReceived`

Fired when a position request completes (success or failure).

| Parameter   | Type      | Description                                   |
|-------------|-----------|-----------------------------------------------|
| `success`   | `bool`    | Whether location was retrieved successfully    |
| `latitude`  | `float`   | Latitude (when successful)                     |
| `longitude` | `float`   | Longitude (when successful)                    |
| `accuracy`  | `float`   | Accuracy in metres (when successful)           |
| `timestamp` | `int`     | Unix timestamp of the location fix             |
| `provider`  | `string`  | Provider used: `gps`, `network`, `fused`, etc. |
| `id`        | `string`  | ID passed to `getCurrentPosition()` (if any)   |
| `error`     | `string`  | Error message (when unsuccessful)              |

### `PermissionStatusReceived`

Fired after `checkPermissions()`.

| Parameter | Type     | Values                                  |
|-----------|----------|-----------------------------------------|
| `status`  | `string` | `granted` \| `denied` \| `not_determined` |

### `PermissionRequestResult`

Fired after `requestPermissions()`.

| Parameter | Type     | Values                                                  |
|-----------|----------|---------------------------------------------------------|
| `status`  | `string` | `granted` \| `denied` \| `permanently_denied`           |

---

## Required Permissions

### Android (`AndroidManifest.xml` — auto-applied via `nativephp.json`)

- `android.permission.ACCESS_FINE_LOCATION`
- `android.permission.ACCESS_COARSE_LOCATION`

### iOS (`Info.plist` — auto-applied via `nativephp.json`)

- `NSLocationWhenInUseUsageDescription`
- `NSLocationAlwaysAndWhenInUseUsageDescription`

---

## Privacy & Performance Tips

- Always explain why you need location before requesting permission.
- Request at the right time — when the feature is actively needed.
- If the user permanently denies, direct them to system settings.
- Use `highAccuracy: false` (network) unless GPS precision is required — it saves battery and is faster.
- Consider caching recent results to avoid redundant GPS fixes.

---

## Changelog

### 1.0.0
- Initial release: `getCurrentPosition`, `checkPermissions`, `requestPermissions`
- iOS (CoreLocation) and Android (LocationManager) implementations
- Livewire + Vue/React/Inertia support
