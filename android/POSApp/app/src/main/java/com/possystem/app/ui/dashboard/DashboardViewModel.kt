package com.possystem.app.ui.dashboard

import androidx.lifecycle.LiveData
import androidx.lifecycle.MutableLiveData
import androidx.lifecycle.ViewModel
import androidx.lifecycle.viewModelScope
import com.possystem.app.data.model.DashboardData
import com.possystem.app.data.repository.POSRepository
import kotlinx.coroutines.launch

class DashboardViewModel : ViewModel() {
    private val repository = POSRepository()

    private val _isLoading = MutableLiveData(false)
    val isLoading: LiveData<Boolean> = _isLoading

    private val _error = MutableLiveData<String?>()
    val error: LiveData<String?> = _error

    private val _dashboardData = MutableLiveData<DashboardData?>()
    val dashboardData: LiveData<DashboardData?> = _dashboardData

    fun loadDashboard() {
        viewModelScope.launch {
            _isLoading.value = true
            _error.value = null

            repository.getDashboard().fold(
                onSuccess = { _dashboardData.value = it },
                onFailure = { _error.value = it.message }
            )
            _isLoading.value = false
        }
    }
}
