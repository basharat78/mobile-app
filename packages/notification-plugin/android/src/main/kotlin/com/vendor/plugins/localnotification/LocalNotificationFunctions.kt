package com.vendor.plugins.localnotification

import android.app.NotificationChannel
import android.app.NotificationManager
import android.app.PendingIntent
import android.content.Context
import android.content.Intent
import android.graphics.Color
import android.media.AudioAttributes
import android.media.RingtoneManager
import android.net.Uri
import android.os.Build
import androidx.core.app.NotificationCompat
import androidx.core.app.NotificationManagerCompat
import com.nativephp.mobile.bridge.BridgeFunction
import com.nativephp.mobile.bridge.BridgeResponse

// ---------------------------------------------------------------------------
// Helper: dispatch a NativePHP event back to PHP/Livewire
// ---------------------------------------------------------------------------
private fun dispatchNativeEvent(context: Context, eventClass: String, payload: Map<String, Any>) {
    val intent = Intent("com.nativephp.mobile.EVENT").apply {
        putExtra("event", eventClass)
        putExtra("payload", org.json.JSONObject(payload).toString())
        setPackage(context.packageName)
    }
    context.sendBroadcast(intent)
}

// ---------------------------------------------------------------------------
// Broadcast receiver for tap / dismiss actions
// ---------------------------------------------------------------------------
class NotificationActionReceiver : android.content.BroadcastReceiver() {
    override fun onReceive(context: Context, intent: Intent) {
        val id   = intent.getStringExtra("notification_id") ?: return
        val data = intent.getStringExtra("notification_data") ?: "{}"

        val payload = try {
            val json = org.json.JSONObject(data)
            json.keys().asSequence().associateWith { json.get(it) as Any }
        } catch (e: Exception) {
            emptyMap()
        }

        when (intent.action) {
            "com.vendor.plugins.localnotification.ACTION_TAP" -> {
                dispatchNativeEvent(
                    context,
                    "Vendor\\LocalNotification\\Events\\NotificationTapped",
                    mapOf("id" to id, "data" to payload)
                )
                // Bring app to foreground
                val launchIntent = context.packageManager.getLaunchIntentForPackage(context.packageName)
                launchIntent?.addFlags(Intent.FLAG_ACTIVITY_NEW_TASK)
                context.startActivity(launchIntent)
            }
            "com.vendor.plugins.localnotification.ACTION_DISMISS" -> {
                dispatchNativeEvent(
                    context,
                    "Vendor\\LocalNotification\\Events\\NotificationDismissed",
                    mapOf("id" to id, "data" to payload)
                )
            }
        }

        // Remove the notification from the status bar
        val manager = context.getSystemService(Context.NOTIFICATION_SERVICE) as NotificationManager
        manager.cancel(id.hashCode())
    }
}

// ---------------------------------------------------------------------------
// Bridge Functions
// ---------------------------------------------------------------------------
object LocalNotificationFunctions {

    // ------------------------------------------------------------------
    // Show
    // ------------------------------------------------------------------
    class Show : BridgeFunction {
        override fun execute(parameters: Map<String, Any>): Map<String, Any> {
            val context = com.nativephp.mobile.NativeApp.context
                ?: return BridgeResponse.error("No application context available")

            val id         = parameters["id"] as? String ?: return BridgeResponse.error("id is required")
            val title      = parameters["title"] as? String ?: ""
            val body       = parameters["body"] as? String ?: ""
            val soundName  = parameters["sound"] as? String ?: "default"
            val badge      = (parameters["badge"] as? Number)?.toInt()
            val channelId  = parameters["channelId"] as? String ?: "default"
            val dataMap    = parameters["data"] as? Map<*, *> ?: emptyMap<String, Any>()
            val ongoing    = parameters["ongoing"] as? Boolean ?: false
            val priority   = parameters["priority"] as? String ?: "high"
            val iconName   = parameters["icon"] as? String ?: "ic_notification"
            val scheduleAt = (parameters["scheduleAt"] as? Number)?.toLong()
            val group      = parameters["group"] as? String

            val dataJson   = org.json.JSONObject(dataMap as Map<*, *>).toString()
            val notifIntId = id.hashCode()

            // Tap pending intent
            val tapIntent = Intent("com.vendor.plugins.localnotification.ACTION_TAP").apply {
                putExtra("notification_id", id)
                putExtra("notification_data", dataJson)
                setPackage(context.packageName)
            }
            val tapPending = PendingIntent.getBroadcast(
                context, notifIntId, tapIntent,
                PendingIntent.FLAG_UPDATE_CURRENT or PendingIntent.FLAG_IMMUTABLE
            )

            // Dismiss pending intent
            val dismissIntent = Intent("com.vendor.plugins.localnotification.ACTION_DISMISS").apply {
                putExtra("notification_id", id)
                putExtra("notification_data", dataJson)
                setPackage(context.packageName)
            }
            val dismissPending = PendingIntent.getBroadcast(
                context, notifIntId + 1, dismissIntent,
                PendingIntent.FLAG_UPDATE_CURRENT or PendingIntent.FLAG_IMMUTABLE
            )

            // Resolve icon resource
            val iconRes = context.resources.getIdentifier(iconName, "drawable", context.packageName)
                .takeIf { it != 0 }
                ?: android.R.drawable.ic_dialog_info

            // Resolve sound URI
            val soundUri: Uri? = when (soundName) {
                "none"    -> null
                "default" -> RingtoneManager.getDefaultUri(RingtoneManager.TYPE_NOTIFICATION)
                else      -> {
                    val resId = context.resources.getIdentifier(soundName, "raw", context.packageName)
                    if (resId != 0) Uri.parse("android.resource://${context.packageName}/$resId")
                    else RingtoneManager.getDefaultUri(RingtoneManager.TYPE_NOTIFICATION)
                }
            }

            // Map priority
            val notifPriority = when (priority) {
                "low"    -> NotificationCompat.PRIORITY_LOW
                "default"-> NotificationCompat.PRIORITY_DEFAULT
                else     -> NotificationCompat.PRIORITY_HIGH
            }

            val builder = NotificationCompat.Builder(context, channelId)
                .setSmallIcon(iconRes)
                .setContentTitle(title)
                .setContentText(body)
                .setStyle(NotificationCompat.BigTextStyle().bigText(body))
                .setPriority(notifPriority)
                .setAutoCancel(true)
                .setOngoing(ongoing)
                .setContentIntent(tapPending)
                .setDeleteIntent(dismissPending)
                .setVisibility(NotificationCompat.VISIBILITY_PUBLIC)

            soundUri?.let { builder.setSound(it) } ?: run { builder.setSilent(true) }

            if (soundName != "none") {
                builder.setVibrate(longArrayOf(0, 250, 250, 250))
            }

            group?.let {
                builder.setGroup(it)
                builder.setGroupSummary(false)
            }

            badge?.let { builder.setNumber(it) }

            val manager = NotificationManagerCompat.from(context)
            manager.notify(notifIntId, builder.build())

            return BridgeResponse.success(mapOf("id" to id))
        }
    }

    // ------------------------------------------------------------------
    // RequestPermission
    // ------------------------------------------------------------------
    class RequestPermission : BridgeFunction {
        override fun execute(parameters: Map<String, Any>): Map<String, Any> {
            val context = com.nativephp.mobile.NativeApp.context
                ?: return BridgeResponse.error("No application context available")

            return if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.TIRAMISU) {
                // On Android 13+ (API 33) we need POST_NOTIFICATIONS at runtime.
                // The actual dialog must be triggered from an Activity. We dispatch
                // a NativePHP event that the app can handle to request the permission
                // via the main activity, then return the current status.
                dispatchNativeEvent(
                    context,
                    "Vendor\\LocalNotification\\Events\\NotificationPermissionChanged",
                    mapOf("granted" to false, "status" to "requesting")
                )
                BridgeResponse.success(mapOf(
                    "granted" to false,
                    "status"  to "requesting",
                    "message" to "Permission dialog will be shown. Listen for NotificationPermissionChanged event."
                ))
            } else {
                // Below Android 13, permission is granted at install time
                BridgeResponse.success(mapOf("granted" to true, "status" to "granted"))
            }
        }
    }

    // ------------------------------------------------------------------
    // CheckPermission
    // ------------------------------------------------------------------
    class CheckPermission : BridgeFunction {
        override fun execute(parameters: Map<String, Any>): Map<String, Any> {
            val context = com.nativephp.mobile.NativeApp.context
                ?: return BridgeResponse.error("No application context available")

            val manager = NotificationManagerCompat.from(context)
            val granted = manager.areNotificationsEnabled()
            return BridgeResponse.success(mapOf(
                "granted" to granted,
                "status"  to if (granted) "granted" else "denied"
            ))
        }
    }

    // ------------------------------------------------------------------
    // Cancel
    // ------------------------------------------------------------------
    class Cancel : BridgeFunction {
        override fun execute(parameters: Map<String, Any>): Map<String, Any> {
            val context = com.nativephp.mobile.NativeApp.context
                ?: return BridgeResponse.error("No application context available")
            val id = parameters["id"] as? String ?: return BridgeResponse.error("id is required")

            val manager = context.getSystemService(Context.NOTIFICATION_SERVICE) as NotificationManager
            manager.cancel(id.hashCode())
            return BridgeResponse.success(mapOf("cancelled" to id))
        }
    }

    // ------------------------------------------------------------------
    // CancelAll
    // ------------------------------------------------------------------
    class CancelAll : BridgeFunction {
        override fun execute(parameters: Map<String, Any>): Map<String, Any> {
            val context = com.nativephp.mobile.NativeApp.context
                ?: return BridgeResponse.error("No application context available")

            val manager = context.getSystemService(Context.NOTIFICATION_SERVICE) as NotificationManager
            manager.cancelAll()
            return BridgeResponse.success(mapOf("cancelled" to "all"))
        }
    }

    // ------------------------------------------------------------------
    // SetBadge
    // ------------------------------------------------------------------
    class SetBadge : BridgeFunction {
        override fun execute(parameters: Map<String, Any>): Map<String, Any> {
            val context = com.nativephp.mobile.NativeApp.context
                ?: return BridgeResponse.error("No application context available")
            val count = (parameters["count"] as? Number)?.toInt() ?: 0

            // Badge support varies by launcher; use ShortcutBadger for broad coverage
            try {
                val clazz = Class.forName("me.leolin.shortcutbadger.ShortcutBadger")
                if (count > 0) {
                    clazz.getMethod("applyCount", Context::class.java, Int::class.java)
                        .invoke(null, context, count)
                } else {
                    clazz.getMethod("removeCount", Context::class.java)
                        .invoke(null, context)
                }
            } catch (e: Exception) {
                // ShortcutBadger not available — badge silently ignored
            }

            return BridgeResponse.success(mapOf("badge" to count))
        }
    }

    // ------------------------------------------------------------------
    // CreateChannel  (Android 8+ / API 26+)
    // ------------------------------------------------------------------
    class CreateChannel : BridgeFunction {
        override fun execute(parameters: Map<String, Any>): Map<String, Any> {
            if (Build.VERSION.SDK_INT < Build.VERSION_CODES.O) {
                return BridgeResponse.success(mapOf("created" to false, "reason" to "API < 26"))
            }

            val context = com.nativephp.mobile.NativeApp.context
                ?: return BridgeResponse.error("No application context available")

            val id          = parameters["id"] as? String ?: return BridgeResponse.error("id is required")
            val name        = parameters["name"] as? String ?: id
            val description = parameters["description"] as? String ?: ""
            val importance  = when (parameters["importance"] as? String) {
                "low"    -> NotificationManager.IMPORTANCE_LOW
                "none"   -> NotificationManager.IMPORTANCE_NONE
                "default"-> NotificationManager.IMPORTANCE_DEFAULT
                else     -> NotificationManager.IMPORTANCE_HIGH
            }
            val withSound     = parameters["sound"] as? Boolean ?: true
            val withVibration = parameters["vibration"] as? Boolean ?: true
            val withLights    = parameters["lights"] as? Boolean ?: true

            val channel = NotificationChannel(id, name, importance).apply {
                this.description = description
                enableVibration(withVibration)
                enableLights(withLights)
                lightColor = Color.GREEN

                if (withSound) {
                    val audioAttr = AudioAttributes.Builder()
                        .setContentType(AudioAttributes.CONTENT_TYPE_SONIFICATION)
                        .setUsage(AudioAttributes.USAGE_NOTIFICATION)
                        .build()
                    setSound(RingtoneManager.getDefaultUri(RingtoneManager.TYPE_NOTIFICATION), audioAttr)
                } else {
                    setSound(null, null)
                }
            }

            val manager = context.getSystemService(Context.NOTIFICATION_SERVICE) as NotificationManager
            manager.createNotificationChannel(channel)

            return BridgeResponse.success(mapOf("created" to true, "id" to id))
        }
    }
}
