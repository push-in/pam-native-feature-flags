import Foundation
import PamNative

public final class FeatureFlagSnapshotModule: NativeModule, @unchecked Sendable {
    private let defaults: UserDefaults
    private let maxBytes = 1024 * 1024

    public init(defaults: UserDefaults = .standard) {
        self.defaults = defaults
    }

    public func invoke(method: String, payload: Data, completion: @escaping ModuleCompletion) {
        do {
            let values = try WireMap.decode(payload)
            guard case let .text(namespace)? = values["namespace"],
                  namespace.range(of: "^[A-Za-z0-9._-]{1,128}$", options: .regularExpression) != nil
            else {
                throw SnapshotError.invalidNamespace
            }
            switch method {
            case "save":
                guard case let .text(snapshot)? = values["snapshot"],
                      snapshot.utf8.count <= maxBytes else {
                    throw SnapshotError.invalidSnapshot
                }
                defaults.set(snapshot, forKey: key(namespace))
                completion(.success, try WireMap.encode(["saved": .flag(true)]))
            case "load":
                let snapshot = defaults.string(forKey: key(namespace)) ?? ""
                completion(.success, try WireMap.encode(["snapshot": .text(snapshot)]))
            case "delete":
                defaults.removeObject(forKey: key(namespace))
                completion(.success, try WireMap.encode(["deleted": .flag(true)]))
            default:
                completion(.failure, Data("Unknown method: \(method)".utf8))
            }
        } catch {
            completion(.failure, Data(String(describing: error).utf8))
        }
    }

    private func key(_ namespace: String) -> String {
        "dev.pam.feature-flags.\(namespace)"
    }
}

private enum SnapshotError: Error {
    case invalidNamespace
    case invalidSnapshot
}
