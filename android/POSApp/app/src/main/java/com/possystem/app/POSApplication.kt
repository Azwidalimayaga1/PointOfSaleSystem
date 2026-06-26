package com.possystem.app

import android.app.Application
import com.possystem.app.data.local.PreferencesManager

class POSApplication : Application() {
    lateinit var preferencesManager: PreferencesManager
        private set

    override fun onCreate() {
        super.onCreate()
        instance = this
        preferencesManager = PreferencesManager(this)
    }

    companion object {
        lateinit var instance: POSApplication
            private set
    }
}
