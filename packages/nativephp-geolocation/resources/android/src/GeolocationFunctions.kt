package com.vendor.plugins.geolocation

import android.Manifest
import android.content.pm.PackageManager
import android.location.Location
import android.location.LocationListener
import android.location.LocationManager
import android.os.Bundle
import androidx.core.content.ContextCompat
import com.nativephp.mobile.bridge.BridgeFunction
import com.nativephp.mobile.bridge.BridgeResponse
import com.nativephp.mobile.NativeApp
import java.util.concurrent.CountDownLatch
import java.util.concurrent.TimeUnit

object GeolocationFunctions {

    // -------------------------------------------------------------------------
    // GetCurrentPosition
    // -------------------------------------------------------------------------

    class GetCurrentPosition : BridgeFunction {
        override fun execute(parameters: Map<String, Any>): Map<String, Any> {
            val highAccuracy = parameters["high_accuracy"] as? Boolean ?: false
            val id = parameters["id"] as? String

            val context = NativeApp.context
            val locationManager =
                context.getSystemService(android.content.Context.LOCATION_SERVICE) as LocationManager

            // Check that at least one permission is granted
            val hasFine = ContextCompat.checkSelfPermission(
                context, Manifest.permission.ACCESS_FINE_LOCATION
            ) == PackageManager.PERMISSION_GRANTED

            val hasCoarse = ContextCompat.checkSelfPermission(
                context, Manifest.permission.ACCESS_COARSE_LOCATION
            ) == PackageManager.PERMISSION_GRANTED

            if (!hasFine && !hasCoarse) {
                val error = mapOf<String, Any>(
                    "success" to false,
                    "error" to "Location permission not granted"
                )
                return BridgeResponse.success(error)
            }

            val provider = if (highAccuracy && locationManager.isProviderEnabled(LocationManager.GPS_PROVIDER)) {
                LocationManager.GPS_PROVIDER
            } else {
                LocationManager.NETWORK_PROVIDER
            }

            val latch = CountDownLatch(1)
            var locationResult: Map<String, Any>? = null

            val listener = object : LocationListener {
                override fun onLocationChanged(location: Location) {
                    val data = mutableMapOf<String, Any>(
                        "success" to true,
                        "latitude" to location.latitude,
                        "longitude" to location.longitude,
                        "accuracy" to location.accuracy.toDouble(),
                        "timestamp" to (location.time / 1000L),
                        "provider" to location.provider.orEmpty()
                    )
                    id?.let { data["id"] = it }
                    locationResult = data
                    latch.countDown()
                }

                @Deprecated("Deprecated in Java")
                override fun onStatusChanged(provider: String?, status: Int, extras: Bundle?) {}
                override fun onProviderEnabled(provider: String) {}
                override fun onProviderDisabled(provider: String) {
                    val data = mutableMapOf<String, Any>(
                        "success" to false,
                        "error" to "Location provider disabled: $provider"
                    )
                    id?.let { data["id"] = it }
                    locationResult = data
                    latch.countDown()
                }
            }

            try {
                locationManager.requestSingleUpdate(provider, listener, null)
            } catch (e: SecurityException) {
                return BridgeResponse.success(
                    mapOf("success" to false, "error" to "Security exception: ${e.message}")
                )
            }

            // Wait up to 30 seconds for a fix
            latch.await(30, TimeUnit.SECONDS)
            locationManager.removeUpdates(listener)

            return BridgeResponse.success(
                locationResult ?: mapOf("success" to false, "error" to "Location request timed out")
            )
        }
    }

    // -------------------------------------------------------------------------
    // CheckPermissions
    // -------------------------------------------------------------------------

    class CheckPermissions : BridgeFunction {
        override fun execute(parameters: Map<String, Any>): Map<String, Any> {
            val context = NativeApp.context

            val hasFine = ContextCompat.checkSelfPermission(
                context, Manifest.permission.ACCESS_FINE_LOCATION
            ) == PackageManager.PERMISSION_GRANTED

            val hasCoarse = ContextCompat.checkSelfPermission(
                context, Manifest.permission.ACCESS_COARSE_LOCATION
            ) == PackageManager.PERMISSION_GRANTED

            val status = if (hasFine || hasCoarse) "granted" else "denied"
            return BridgeResponse.success(mapOf("status" to status))
        }
    }

    // -------------------------------------------------------------------------
    // RequestPermissions
    // -------------------------------------------------------------------------

    class RequestPermissions : BridgeFunction {
        override fun execute(parameters: Map<String, Any>): Map<String, Any> {
            // On Android, runtime permission requests must be initiated from the
            // Activity layer. NativePHP's permission bridge handles the actual
            // system dialog — this function signals intent and the result arrives
            // via the PermissionRequestResult event.
            return BridgeResponse.success(mapOf("status" to "requested"))
        }
    }
}
