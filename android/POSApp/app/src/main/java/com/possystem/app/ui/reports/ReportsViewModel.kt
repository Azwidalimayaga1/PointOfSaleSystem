package com.possystem.app.ui.reports

import androidx.lifecycle.LiveData
import androidx.lifecycle.MutableLiveData
import androidx.lifecycle.ViewModel
import androidx.lifecycle.viewModelScope
import com.possystem.app.data.model.ReportData
import com.possystem.app.data.repository.POSRepository
import kotlinx.coroutines.launch

class ReportsViewModel : ViewModel() {
    private val repository = POSRepository()

    private val _isLoading = MutableLiveData(false)
    val isLoading: LiveData<Boolean> = _isLoading

    private val _reportData = MutableLiveData<ReportData?>()
    val reportData: LiveData<ReportData?> = _reportData

    private val _error = MutableLiveData<String?>()
    val error: LiveData<String?> = _error

    private val _selectedReport = MutableLiveData("sales")
    val selectedReport: LiveData<String> = _selectedReport

    fun loadReport(type: String, period: String? = null) {
        _selectedReport.value = type
        viewModelScope.launch {
            _isLoading.value = true
            _error.value = null

            repository.getReport(type, period).fold(
                onSuccess = { _reportData.value = it },
                onFailure = { _error.value = it.message }
            )
            _isLoading.value = false
        }
    }
}
