package com.possystem.app.data.local

import android.content.Context
import androidx.datastore.core.DataStore
import androidx.datastore.preferences.core.Preferences
import androidx.datastore.preferences.core.edit
import androidx.datastore.preferences.core.intPreferencesKey
import androidx.datastore.preferences.core.stringPreferencesKey
import androidx.datastore.preferences.preferencesDataStore
import kotlinx.coroutines.flow.Flow
import kotlinx.coroutines.flow.map

private val Context.dataStore: DataStore<Preferences> by preferencesDataStore(name = "pos_prefs")

class PreferencesManager(private val context: Context) {
    companion object {
        private val ACCESS_TOKEN = stringPreferencesKey("access_token")
        private val REFRESH_TOKEN = stringPreferencesKey("refresh_token")
        private val USER_ID = intPreferencesKey("user_id")
        private val USERNAME = stringPreferencesKey("username")
        private val FULL_NAME = stringPreferencesKey("full_name")
        private val ROLE = stringPreferencesKey("role")
        private val STORE_ID = intPreferencesKey("store_id")
    }

    val accessToken: Flow<String?> = context.dataStore.data.map { it[ACCESS_TOKEN] }
    val refreshToken: Flow<String?> = context.dataStore.data.map { it[REFRESH_TOKEN] }
    val userId: Flow<Int?> = context.dataStore.data.map { it[USER_ID] }
    val username: Flow<String?> = context.dataStore.data.map { it[USERNAME] }
    val fullName: Flow<String?> = context.dataStore.data.map { it[FULL_NAME] }
    val role: Flow<String?> = context.dataStore.data.map { it[ROLE] }
    val storeId: Flow<Int?> = context.dataStore.data.map { it[STORE_ID] }

    suspend fun saveAuthData(
        accessToken: String,
        refreshToken: String,
        userId: Int,
        username: String,
        fullName: String,
        role: String,
        storeId: Int?
    ) {
        context.dataStore.edit { prefs ->
            prefs[ACCESS_TOKEN] = accessToken
            prefs[REFRESH_TOKEN] = refreshToken
            prefs[USER_ID] = userId
            prefs[USERNAME] = username
            prefs[FULL_NAME] = fullName
            prefs[ROLE] = role
            if (storeId != null) prefs[STORE_ID] = storeId
        }
    }

    suspend fun clearSession() {
        context.dataStore.edit { it.clear() }
    }
}
