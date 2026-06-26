package com.possystem.app.ui.auth

import androidx.lifecycle.LiveData
import androidx.lifecycle.MutableLiveData
import androidx.lifecycle.ViewModel
import androidx.lifecycle.viewModelScope
import com.possystem.app.data.repository.POSRepository
import com.possystem.app.util.SessionManager
import kotlinx.coroutines.launch

class AuthViewModel : ViewModel() {
    private val repository = POSRepository()

    private val _isLoading = MutableLiveData(false)
    val isLoading: LiveData<Boolean> = _isLoading

    private val _error = MutableLiveData<String?>()
    val error: LiveData<String?> = _error

    private val _isLoggedIn = MutableLiveData(false)
    val isLoggedIn: LiveData<Boolean> = _isLoggedIn

    fun login(username: String, password: String) {
        viewModelScope.launch {
            _isLoading.value = true
            _error.value = null

            repository.login(username, password).fold(
                onSuccess = { response ->
                    val user = response.user
                    if (user != null) {
                        SessionManager.saveSession(
                            accessToken = response.accessToken,
                            refreshToken = response.refreshToken,
                            user = user
                        )
                        _isLoggedIn.value = true
                    } else {
                        _error.value = "Login failed: no user data"
                    }
                },
                onFailure = { e ->
                    _error.value = e.message ?: "Login failed"
                }
            )
            _isLoading.value = false
        }
    }

    fun logout() {
        viewModelScope.launch {
            SessionManager.clearSession()
            _isLoggedIn.value = false
        }
    }
}
