package com.casebook.casebook

import android.content.Context
import android.os.Bundle
import io.flutter.embedding.android.FlutterActivity
import java.io.File

class MainActivity : FlutterActivity() {
    override fun onCreate(savedInstanceState: Bundle?) {
        clearMissingOneSignalUser()
        super.onCreate(savedInstanceState)
    }

    // A rejected login left a OneSignal user id on the device. The server
    // returns 404 for that id, and later logins keep patching it.
    private fun clearMissingOneSignalUser() {
        val marker = getSharedPreferences("casebook_push", Context.MODE_PRIVATE)
        if (marker.getBoolean("cleared_missing_user", false)) {
            return
        }
        val prefsDir = File(applicationInfo.dataDir, "shared_prefs")
        prefsDir.listFiles()?.forEach { file ->
            val name = file.name
            if (name.startsWith("OneSignal") && name.endsWith(".xml")) {
                getSharedPreferences(name.removeSuffix(".xml"), Context.MODE_PRIVATE)
                    .edit()
                    .clear()
                    .commit()
            }
        }
        getSharedPreferences("OneSignal", Context.MODE_PRIVATE).edit().clear().commit()
        databaseList()?.forEach { name ->
            if (name.contains("OneSignal", ignoreCase = true)) {
                deleteDatabase(name)
            }
        }
        marker.edit().putBoolean("cleared_missing_user", true).commit()
    }
}
