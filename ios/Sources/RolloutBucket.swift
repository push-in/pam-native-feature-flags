import CryptoKit
import Foundation

public enum RolloutBucket {
    public static func calculate(key: String, bucketingId: String) -> Int {
        let digest = SHA256.hash(data: Data("\(key)\0\(bucketingId)".utf8))
        let prefix = digest.prefix(4).reduce(UInt32.zero) { value, byte in
            (value << 8) | UInt32(byte)
        }
        return Int(prefix % 10_000)
    }
}
