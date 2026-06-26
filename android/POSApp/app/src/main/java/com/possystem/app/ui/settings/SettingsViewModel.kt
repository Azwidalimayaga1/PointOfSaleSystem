package com.possystem.app.ui.settings

import androidx.lifecycle.LiveData
import androidx.lifecycle.MutableLiveData
import androidx.lifecycle.ViewModel
import androidx.lifecycle.viewModelScope
import com.possystem.app.data.model.SettingUpdate
import com.possystem.app.data.model.Settings
import com.possystem.app.data.repository.POSRepository
import kotlinx.coroutines.launch

class SettingsViewModel : ViewModel() {
    private val repository = POSRepository()

    private val _isLoading = MutableLiveData(false)
    val isLoading: LiveData<Boolean> = _isLoading

    private val _settings = MutableLiveData<Settings?>()
    val settings: LiveData<Settings?> = _settings

    private val _error = MutableLiveData<String?>()
    val error: LiveData<String?> = _error

    private val _success = MutableLiveData<String?>()
    val success: LiveData<String?> = _success

    fun loadSettings() {
        viewModelScope.launch {
            _isLoading.value = true
            repository.getSettings().fold(
                onSuccess = { _settings.value = it },
                onFailure = { _error.value = it.message }
            )
            _isLoading.value = false
        }
    }

    fun updateSettings(update: SettingUpdate) {
        viewModelScope.launch {
            _isLoading.value = true
            repository.updateSettings(update).fold(
                onSuccess = {
                    _settings.value = it
                    _success.value = "Settings updated"
                },
                onFailure = { _error.value = it.message }
            )
            _isLoading.value = false
        }
    }
}
