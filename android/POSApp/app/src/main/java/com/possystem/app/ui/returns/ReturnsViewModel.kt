package com.possystem.app.ui.returns

import androidx.lifecycle.LiveData
import androidx.lifecycle.MutableLiveData
import androidx.lifecycle.ViewModel
import androidx.lifecycle.viewModelScope
import com.possystem.app.data.model.*
import com.possystem.app.data.repository.POSRepository
import kotlinx.coroutines.launch

class ReturnsViewModel : ViewModel() {
    private val repository = POSRepository()

    private val _isLoading = MutableLiveData(false)
    val isLoading: LiveData<Boolean> = _isLoading

    private val _returns = MutableLiveData<List<ReturnRequest>>()
    val returns: LiveData<List<ReturnRequest>> = _returns

    private val _error = MutableLiveData<String?>()
    val error: LiveData<String?> = _error

    fun loadReturns(period: String? = null, status: String? = null) {
        viewModelScope.launch {
            _isLoading.value = true
            repository.getReturns(period, status).fold(
                onSuccess = { _returns.value = it },
                onFailure = { _error.value = it.message }
            )
            _isLoading.value = false
        }
    }

    fun submitReturn(
        saleId: Int,
        items: List<Pair<Int, Int>>,
        reason: String,
        resolution: String,
        onSuccess: () -> Unit
    ) {
        viewModelScope.launch {
            _isLoading.value = true
            _error.value = null

            val request = ReturnSubmitRequest(
                saleId = saleId,
                items = items.map { ReturnItemRequest(it.first, it.second) },
                reason = reason,
                resolution = resolution
            )

            repository.submitReturn(request).fold(
                onSuccess = { onSuccess() },
                onFailure = { _error.value = it.message }
            )
            _isLoading.value = false
        }
    }
}
