# NativePHP Local Notification Plugin

WhatsApp-style local notifications with sound, badge, and tap handling for **NativePHP Mobile v3** — works on both **iOS** and **Android**.

---

## Features

- ✅ Show notifications immediately or scheduled
- ✅ Custom / default sound (plays even in foreground, like WhatsApp)
- ✅ App icon badge count
- ✅ Tap & dismiss events dispatched to PHP / Livewire
- ✅ Android notification channels (API 26+)
- ✅ Notification grouping
- ✅ Permission request & check
- ✅ Cancel individual or all notifications
- ✅ JavaScript bridge for Vue / React / Livewire

---

## Installation

### 1. Require the package

```bash
composer require vendor/nativephp-local-notification
```

### 2. Register the plugin

```bash
php artisan vendor:publish --tag=nativephp-plugins-provider   # first time only
php artisan native:plugin:register vendor/nativephp-local-notification
```

### 3. Rebuild native projects

```bash
php artisan native:install --force
php artisan native:run android   # or ios
```

---

## PHP Usage

### Show a notification

```php
use Vendor\LocalNotification\Facades\LocalNotification;

// Simple
LocalNotification::show('New Message', 'Hey, how are you?');

// With options
LocalNotification::show('New Message', 'Hey, how are you?', [
    'id'        => 'msg_42',
    'sound'     => 'default',        // 'default' | 'none' | 'custom_sound'
    'badge'     => 3,
    'channelId' => 'messages',       // Android only
    'data'      => ['chat_id' => 5], // passed back in NotificationTapped event
    'priority'  => 'high',
]);

// Scheduled (10 minutes from now)
LocalNotification::show('Reminder', 'Don\'t forget!', [
    'scheduleAt' => now()->addMinutes(10)->timestamp,
]);
```

### Request / check permission

```php
$result = LocalNotification::requestPermission();
// ['granted' => true, 'status' => 'granted']

$result = LocalNotification::checkPermission();
// ['granted' => true, 'status' => 'granted']
```

### Manage notifications

```php
LocalNotification::cancel('msg_42');   // cancel one
LocalNotification::cancelAll();        // cancel all
LocalNotification::setBadge(0);        // clear badge
```

### Android channels (set up once at app start)

```php
// In AppServiceProvider::boot() or a lifecycle hook
LocalNotification::createChannel(
    id:          'messages',
    name:        'Messages',
    importance:  'high',
    description: 'New message notifications',
    sound:       true,
    vibration:   true,
    lights:      true,
);
```

---

## Listening for Events

Register listeners in `EventServiceProvider`:

```php
use Vendor\LocalNotification\Events\NotificationTapped;
use Vendor\LocalNotification\Events\NotificationDismissed;
use Vendor\LocalNotification\Events\NotificationPermissionChanged;

protected $listen = [
    NotificationTapped::class => [
        \App\Listeners\HandleNotificationTap::class,
    ],
    NotificationDismissed::class => [
        \App\Listeners\HandleNotificationDismiss::class,
    ],
    NotificationPermissionChanged::class => [
        \App\Listeners\HandlePermissionChange::class,
    ],
];
```

Example listener:

```php
class HandleNotificationTap
{
    public function handle(NotificationTapped $event): void
    {
        // $event->id   — the notification ID
        // $event->data — your custom payload array
        // e.g. redirect to the right chat screen
    }
}
```

In **Livewire**, you can listen directly on a component:

```php
#[On(NotificationTapped::class)]
public function onNotificationTapped(string $id, array $data): void
{
    // handle tap
}
```

---

## JavaScript Usage

```js
import LocalNotification from './localNotification.js';

// Check / request permission
const { granted } = await LocalNotification.checkPermission();
if (!granted) {
    await LocalNotification.requestPermission();
}

// Show a notification
await LocalNotification.show('New Message', 'Hey!', {
    sound:     'default',
    badge:     1,
    channelId: 'messages',
    data:      { chatId: 5 },
});

// Clear badge on app open
await LocalNotification.setBadge(0);
```

---

## Custom Sound Files

### Android
Place your `.mp3` or `.wav` file in `android/app/src/main/res/raw/` (e.g. `message_sound.mp3`), then:

```php
LocalNotification::show('New Message', 'Hey!', ['sound' => 'message_sound']);
```

### iOS
Add your sound file to the Xcode project (e.g. `message_sound.caf`), then:

```php
LocalNotification::show('New Message', 'Hey!', ['sound' => 'message_sound.caf']);
```

---

## Permissions

### Android
The following permissions are declared automatically via the manifest:

- `android.permission.POST_NOTIFICATIONS` — runtime permission on Android 13+ (API 33)
- `android.permission.VIBRATE`
- `android.permission.RECEIVE_BOOT_COMPLETED`

### iOS
The following `Info.plist` key is added automatically:

- `NSUserNotificationsUsageDescription`

---

## Testing

```bash
php artisan native:plugin:validate vendor/nativephp-local-notification
```
