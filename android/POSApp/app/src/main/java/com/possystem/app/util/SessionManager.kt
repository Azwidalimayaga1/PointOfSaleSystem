package com.possystem.app.util

import com.possystem.app.POSApplication
import com.possystem.app.data.api.RetrofitClient
import com.possystem.app.data.model.User
import kotlinx.coroutines.flow.first
import kotlinx.coroutines.runBlocking

object SessionManager {
    private val prefs = POSApplication.instance.preferencesManager

    suspend fun restoreSession() {
        val token = prefs.accessToken.first()
        val rt = prefs.refreshToken.first()
        if (token != null && rt != null) {
            RetrofitClient.setTokens(token, rt)
        }
    }

    suspend fun saveSession(
        accessToken: String,
        refreshToken: String,
        user: User
    ) {
        RetrofitClient.setTokens(accessToken, refreshToken)
        prefs.saveAuthData(
            accessToken = accessToken,
            refreshToken = refreshToken,
            userId = user.id,
            username = user.username,
            fullName = user.fullName,
            role = user.role,
            storeId = user.storeId
        )
    }

    suspend fun clearSession() {
        RetrofitClient.clearTokens()
        prefs.clearSession()
    }

    fun isLoggedIn(): Boolean {
        return runBlocking {
            prefs.accessToken.first() != null
        }
    }

    suspend fun getRole(): String? = prefs.role.first()
    suspend fun getStoreId(): Int? = prefs.storeId.first()
    suspend fun getFullName(): String? = prefs.fullName.first()
}
