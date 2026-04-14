import Foundation
import UserNotifications
import UIKit

// ---------------------------------------------------------------------------
// Helper: dispatch a NativePHP event back to PHP / Livewire
// ---------------------------------------------------------------------------
private func dispatchNativeEvent(_ eventClass: String, payload: [String: Any]) {
    NotificationCenter.default.post(
        name: NSNotification.Name("NativePHPEvent"),
        object: nil,
        userInfo: ["event": eventClass, "payload": payload]
    )
}

// ---------------------------------------------------------------------------
// Notification delegate — handles foreground display & user actions
// ---------------------------------------------------------------------------
public class LocalNotificationDelegate: NSObject, UNUserNotificationCenterDelegate {

    public static let shared = LocalNotificationDelegate()

    public func userNotificationCenter(
        _ center: UNUserNotificationCenter,
        willPresent notification: UNNotification,
        withCompletionHandler completionHandler: @escaping (UNNotificationPresentationOptions) -> Void
    ) {
        // Show notification even when app is in foreground (like WhatsApp)
        if #available(iOS 14.0, *) {
            completionHandler([.banner, .sound, .badge])
        } else {
            completionHandler([.alert, .sound, .badge])
        }
    }

    public func userNotificationCenter(
        _ center: UNUserNotificationCenter,
        didReceive response: UNNotificationResponse,
        withCompletionHandler completionHandler: @escaping () -> Void
    ) {
        let userInfo  = response.notification.request.content.userInfo
        let notifId   = response.notification.request.identifier
        let data      = userInfo["data"] as? [String: Any] ?? [:]

        switch response.actionIdentifier {
        case UNNotificationDefaultActionIdentifier:
            dispatchNativeEvent(
                "Vendor\\LocalNotification\\Events\\NotificationTapped",
                payload: ["id": notifId, "data": data]
            )
        case UNNotificationDismissActionIdentifier:
            dispatchNativeEvent(
                "Vendor\\LocalNotification\\Events\\NotificationDismissed",
                payload: ["id": notifId, "data": data]
            )
        default:
            break
        }

        completionHandler()
    }
}

// ---------------------------------------------------------------------------
// Bridge Functions
// ---------------------------------------------------------------------------
enum LocalNotificationFunctions {

    // ------------------------------------------------------------------
    // Show
    // ------------------------------------------------------------------
    class Show: BridgeFunction {
        func execute(parameters: [String: Any]) throws -> [String: Any] {
            let id          = parameters["id"] as? String ?? UUID().uuidString
            let title       = parameters["title"] as? String ?? ""
            let body        = parameters["body"] as? String ?? ""
            let soundName   = parameters["sound"] as? String ?? "default"
            let badge       = parameters["badge"] as? Int
            let channelId   = parameters["channelId"] as? String ?? "default" // unused on iOS, kept for API parity
            let data        = parameters["data"] as? [String: Any] ?? [:]
            let scheduleAt  = parameters["scheduleAt"] as? TimeInterval
            let threadId    = parameters["group"] as? String
            let priority    = parameters["priority"] as? String ?? "high"

            let content = UNMutableNotificationContent()
            content.title    = title
            content.body     = body
            content.userInfo = ["data": data, "notificationId": id]

            // Sound
            switch soundName {
            case "none":
                content.sound = nil
            case "default":
                content.sound = .default
            default:
                content.sound = UNNotificationSound(named: UNNotificationSoundName(rawValue: soundName))
            }

            // Badge
            if let badgeCount = badge {
                content.badge = NSNumber(value: badgeCount)
            }

            // Thread identifier for grouping
            if let group = threadId {
                content.threadIdentifier = group
            }

            // Interruption level (iOS 15+)
            if #available(iOS 15.0, *) {
                content.interruptionLevel = priority == "high" ? .timeSensitive : .active
            }

            // Trigger
            let trigger: UNNotificationTrigger?
            if let fireAt = scheduleAt {
                let date       = Date(timeIntervalSince1970: fireAt)
                let components = Calendar.current.dateComponents(
                    [.year, .month, .day, .hour, .minute, .second], from: date
                )
                trigger = UNCalendarNotificationTrigger(dateMatching: components, repeats: false)
            } else {
                trigger = nil // immediate
            }

            let request = UNNotificationRequest(identifier: id, content: content, trigger: trigger)

            var resultError: Error?
            let semaphore = DispatchSemaphore(value: 0)

            UNUserNotificationCenter.current().add(request) { error in
                resultError = error
                semaphore.signal()
            }
            semaphore.wait()

            if let err = resultError {
                return BridgeResponse.error(message: err.localizedDescription)
            }

            return BridgeResponse.success(data: ["id": id])
        }
    }

    // ------------------------------------------------------------------
    // RequestPermission
    // ------------------------------------------------------------------
    class RequestPermission: BridgeFunction {
        func execute(parameters: [String: Any]) throws -> [String: Any] {
            var granted = false
            var status  = "unknown"
            let semaphore = DispatchSemaphore(value: 0)

            // Register delegate so foreground notifications work
            UNUserNotificationCenter.current().delegate = LocalNotificationDelegate.shared

            UNUserNotificationCenter.current().requestAuthorization(
                options: [.alert, .sound, .badge]
            ) { isGranted, _ in
                granted = isGranted
                status  = isGranted ? "granted" : "denied"
                semaphore.signal()
            }
            semaphore.wait()

            dispatchNativeEvent(
                "Vendor\\LocalNotification\\Events\\NotificationPermissionChanged",
                payload: ["granted": granted, "status": status]
            )

            return BridgeResponse.success(data: ["granted": granted, "status": status])
        }
    }

    // ------------------------------------------------------------------
    // CheckPermission
    // ------------------------------------------------------------------
    class CheckPermission: BridgeFunction {
        func execute(parameters: [String: Any]) throws -> [String: Any] {
            var granted = false
            var status  = "unknown"
            let semaphore = DispatchSemaphore(value: 0)

            UNUserNotificationCenter.current().getNotificationSettings { settings in
                switch settings.authorizationStatus {
                case .authorized, .provisional, .ephemeral:
                    granted = true
                    status  = "granted"
                case .denied:
                    granted = false
                    status  = "denied"
                case .notDetermined:
                    granted = false
                    status  = "not_determined"
                @unknown default:
                    granted = false
                    status  = "unknown"
                }
                semaphore.signal()
            }
            semaphore.wait()

            return BridgeResponse.success(data: ["granted": granted, "status": status])
        }
    }

    // ------------------------------------------------------------------
    // Cancel
    // ------------------------------------------------------------------
    class Cancel: BridgeFunction {
        func execute(parameters: [String: Any]) throws -> [String: Any] {
            guard let id = parameters["id"] as? String else {
                return BridgeResponse.error(message: "id is required")
            }

            let center = UNUserNotificationCenter.current()
            center.removePendingNotificationRequests(withIdentifiers: [id])
            center.removeDeliveredNotifications(withIdentifiers: [id])

            return BridgeResponse.success(data: ["cancelled": id])
        }
    }

    // ------------------------------------------------------------------
    // CancelAll
    // ------------------------------------------------------------------
    class CancelAll: BridgeFunction {
        func execute(parameters: [String: Any]) throws -> [String: Any] {
            let center = UNUserNotificationCenter.current()
            center.removeAllPendingNotificationRequests()
            center.removeAllDeliveredNotifications()
            return BridgeResponse.success(data: ["cancelled": "all"])
        }
    }

    // ------------------------------------------------------------------
    // SetBadge
    // ------------------------------------------------------------------
    class SetBadge: BridgeFunction {
        func execute(parameters: [String: Any]) throws -> [String: Any] {
            let count = parameters["count"] as? Int ?? 0
            DispatchQueue.main.async {
                UIApplication.shared.applicationIconBadgeNumber = count
            }
            return BridgeResponse.success(data: ["badge": count])
        }
    }

    // ------------------------------------------------------------------
    // CreateChannel — no-op on iOS (channels are Android only)
    // ------------------------------------------------------------------
    class CreateChannel: BridgeFunction {
        func execute(parameters: [String: Any]) throws -> [String: Any] {
            return BridgeResponse.success(data: [
                "created": false,
                "reason":  "Channels are an Android concept and are not used on iOS."
            ])
        }
    }
}
