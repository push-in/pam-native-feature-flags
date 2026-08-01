package dev.pam.featureflags

import android.content.Context
import dev.pam.nativeapp.modules.ModuleCompletion
import dev.pam.nativeapp.modules.ModuleResultStatus
import dev.pam.nativeapp.modules.NativeModule
import dev.pam.nativeapp.protocol.WireMap
import dev.pam.nativeapp.protocol.WireValue

class FeatureFlagSnapshotModule(
    context: Context,
) : NativeModule {
    private val preferences = context.applicationContext.getSharedPreferences(
        "pam-native-feature-flags",
        Context.MODE_PRIVATE,
    )

    override fun invoke(method: String, payload: ByteArray, completion: ModuleCompletion) {
        runCatching {
            val values = WireMap.decode(payload)
            val namespace = (values["namespace"] as? WireValue.Text)?.value
                ?.takeIf { it.matches(Regex("[A-Za-z0-9._-]{1,128}")) }
                ?: error("Invalid snapshot namespace")
            when (method) {
                "save" -> {
                    val snapshot = (values["snapshot"] as? WireValue.Text)?.value
                        ?: error("Snapshot is required")
                    require(snapshot.toByteArray(Charsets.UTF_8).size <= 1024 * 1024) {
                        "Snapshot exceeds one MiB"
                    }
                    check(preferences.edit().putString(namespace, snapshot).commit()) {
                        "Snapshot commit failed"
                    }
                    WireMap.encode(mapOf("saved" to WireValue.Flag(true)))
                }
                "load" -> WireMap.encode(mapOf(
                    "snapshot" to WireValue.Text(preferences.getString(namespace, "") ?: ""),
                ))
                "delete" -> {
                    check(preferences.edit().remove(namespace).commit()) {
                        "Snapshot delete failed"
                    }
                    WireMap.encode(mapOf("deleted" to WireValue.Flag(true)))
                }
                else -> error("Unknown method: $method")
            }
        }.onSuccess { result ->
            completion.complete(ModuleResultStatus.SUCCESS, result)
        }.onFailure { error ->
            completion.complete(
                ModuleResultStatus.FAILURE,
                (error.message ?: "Feature flag snapshot failed").toByteArray(Charsets.UTF_8),
            )
        }
    }
}
