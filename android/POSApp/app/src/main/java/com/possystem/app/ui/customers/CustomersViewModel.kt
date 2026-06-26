package com.possystem.app.ui.customers

import androidx.lifecycle.LiveData
import androidx.lifecycle.MutableLiveData
import androidx.lifecycle.ViewModel
import androidx.lifecycle.viewModelScope
import com.possystem.app.data.model.Customer
import com.possystem.app.data.model.CustomerRequest
import com.possystem.app.data.repository.POSRepository
import kotlinx.coroutines.launch

class CustomersViewModel : ViewModel() {
    private val repository = POSRepository()

    private val _isLoading = MutableLiveData(false)
    val isLoading: LiveData<Boolean> = _isLoading

    private val _customers = MutableLiveData<List<Customer>>()
    val customers: LiveData<List<Customer>> = _customers

    private val _selectedCustomer = MutableLiveData<Customer?>()
    val selectedCustomer: LiveData<Customer?> = _selectedCustomer

    private val _error = MutableLiveData<String?>()
    val error: LiveData<String?> = _error

    fun loadCustomers(search: String? = null) {
        viewModelScope.launch {
            _isLoading.value = true
            _error.value = null

            repository.getCustomers(search).fold(
                onSuccess = { _customers.value = it },
                onFailure = { _error.value = it.message }
            )
            _isLoading.value = false
        }
    }

    fun loadCustomer(id: Int) {
        viewModelScope.launch {
            _isLoading.value = true
            repository.getCustomer(id).fold(
                onSuccess = { _selectedCustomer.value = it },
                onFailure = { _error.value = it.message }
            )
            _isLoading.value = false
        }
    }

    fun createCustomer(request: CustomerRequest, onSuccess: () -> Unit) {
        viewModelScope.launch {
            _isLoading.value = true
            repository.createCustomer(request).fold(
                onSuccess = { loadCustomers(); onSuccess() },
                onFailure = { _error.value = it.message }
            )
            _isLoading.value = false
        }
    }

    fun updateCustomer(id: Int, request: CustomerRequest, onSuccess: () -> Unit) {
        viewModelScope.launch {
            _isLoading.value = true
            repository.updateCustomer(id, request).fold(
                onSuccess = { loadCustomers(); onSuccess() },
                onFailure = { _error.value = it.message }
            )
            _isLoading.value = false
        }
    }
}
