package dev.pam.featureflags

import java.security.MessageDigest

object RolloutBucket {
    fun calculate(key: String, bucketingId: String): Int {
        val digest = MessageDigest.getInstance("SHA-256")
            .digest("$key\u0000$bucketingId".toByteArray(Charsets.UTF_8))
        val prefix = ((digest[0].toLong() and 0xff) shl 24) or
            ((digest[1].toLong() and 0xff) shl 16) or
            ((digest[2].toLong() and 0xff) shl 8) or
            (digest[3].toLong() and 0xff)
        return (prefix % 10_000L).toInt()
    }
}
