import Foundation
import CoreLocation

// MARK: - Permission delegate helper

private class LocationPermissionDelegate: NSObject, CLLocationManagerDelegate {
    var onPermissionChange: ((CLAuthorizationStatus) -> Void)?

    func locationManagerDidChangeAuthorization(_ manager: CLLocationManager) {
        onPermissionChange?(manager.authorizationStatus)
    }
}

// MARK: - One-shot location helper

private class LocationFetcher: NSObject, CLLocationManagerDelegate {
    private let manager = CLLocationManager()
    private var completion: (([String: Any]) -> Void)?
    private let highAccuracy: Bool
    private let requestId: String?

    init(highAccuracy: Bool, id: String?) {
        self.highAccuracy = highAccuracy
        self.requestId = id
        super.init()
        manager.delegate = self
        manager.desiredAccuracy = highAccuracy
            ? kCLLocationAccuracyBest
            : kCLLocationAccuracyHundredMeters
    }

    func fetch(completion: @escaping ([String: Any]) -> Void) {
        self.completion = completion
        manager.requestWhenInUseAuthorization()
        manager.requestLocation()
    }

    func locationManager(_ manager: CLLocationManager, didUpdateLocations locations: [CLLocation]) {
        guard let location = locations.last else { return }
        var result: [String: Any] = [
            "success": true,
            "latitude": location.coordinate.latitude,
            "longitude": location.coordinate.longitude,
            "accuracy": location.horizontalAccuracy,
            "timestamp": Int(location.timestamp.timeIntervalSince1970),
            "provider": highAccuracy ? "gps" : "network"
        ]
        if let id = requestId { result["id"] = id }
        completion?(result)
    }

    func locationManager(_ manager: CLLocationManager, didFailWithError error: Error) {
        var result: [String: Any] = [
            "success": false,
            "error": error.localizedDescription
        ]
        if let id = requestId { result["id"] = id }
        completion?(result)
    }
}

// Keep strong references alive during async operations
private var activeFetchers: [LocationFetcher] = []
private var activePermDelegates: [LocationPermissionDelegate] = []

// MARK: - Bridge Functions

enum GeolocationFunctions {

    class GetCurrentPosition: BridgeFunction {
        func execute(parameters: [String: Any]) throws -> [String: Any] {
            let highAccuracy = parameters["high_accuracy"] as? Bool ?? false
            let id = parameters["id"] as? String

            let fetcher = LocationFetcher(highAccuracy: highAccuracy, id: id)
            activeFetchers.append(fetcher)

            var resultData: [String: Any]?
            let semaphore = DispatchSemaphore(value: 0)

            fetcher.fetch { data in
                resultData = data
                semaphore.signal()
            }

            semaphore.wait()
            activeFetchers.removeAll { $0 === fetcher }

            return BridgeResponse.success(data: resultData ?? ["success": false, "error": "Unknown error"])
        }
    }

    class CheckPermissions: BridgeFunction {
        func execute(parameters: [String: Any]) throws -> [String: Any] {
            let manager = CLLocationManager()
            let status: String

            switch manager.authorizationStatus {
            case .authorizedWhenInUse, .authorizedAlways:
                status = "granted"
            case .denied, .restricted:
                status = "denied"
            case .notDetermined:
                status = "not_determined"
            @unknown default:
                status = "not_determined"
            }

            return BridgeResponse.success(data: ["status": status])
        }
    }

    class RequestPermissions: BridgeFunction {
        func execute(parameters: [String: Any]) throws -> [String: Any] {
            let manager = CLLocationManager()
            let delegate = LocationPermissionDelegate()
            activePermDelegates.append(delegate)

            var resultStatus = "denied"
            let semaphore = DispatchSemaphore(value: 0)

            delegate.onPermissionChange = { status in
                switch status {
                case .authorizedWhenInUse, .authorizedAlways:
                    resultStatus = "granted"
                case .denied, .restricted:
                    resultStatus = "denied"
                default:
                    resultStatus = "denied"
                }
                semaphore.signal()
            }

            manager.delegate = delegate
            manager.requestWhenInUseAuthorization()
            semaphore.wait()

            activePermDelegates.removeAll { $0 === delegate }
            return BridgeResponse.success(data: ["status": resultStatus])
        }
    }
}
