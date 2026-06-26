package com.possystem.app.ui.inventory

import androidx.lifecycle.LiveData
import androidx.lifecycle.MutableLiveData
import androidx.lifecycle.ViewModel
import androidx.lifecycle.viewModelScope
import com.possystem.app.data.model.InventoryProduct
import com.possystem.app.data.model.StockAdjustmentRequest
import com.possystem.app.data.repository.POSRepository
import kotlinx.coroutines.launch

class InventoryViewModel : ViewModel() {
    private val repository = POSRepository()

    private val _isLoading = MutableLiveData(false)
    val isLoading: LiveData<Boolean> = _isLoading

    private val _inventory = MutableLiveData<List<InventoryProduct>>()
    val inventory: LiveData<List<InventoryProduct>> = _inventory

    private val _selectedProduct = MutableLiveData<InventoryProduct?>()
    val selectedProduct: LiveData<InventoryProduct?> = _selectedProduct

    private val _error = MutableLiveData<String?>()
    val error: LiveData<String?> = _error

    fun loadInventory() {
        viewModelScope.launch {
            _isLoading.value = true
            _error.value = null

            repository.getInventory().fold(
                onSuccess = { _inventory.value = it },
                onFailure = { _error.value = it.message }
            )
            _isLoading.value = false
        }
    }

    fun loadProduct(id: Int) {
        viewModelScope.launch {
            _isLoading.value = true
            repository.getInventoryProduct(id).fold(
                onSuccess = { _selectedProduct.value = it },
                onFailure = { _error.value = it.message }
            )
            _isLoading.value = false
        }
    }

    fun adjustStock(request: StockAdjustmentRequest, onSuccess: () -> Unit) {
        viewModelScope.launch {
            _isLoading.value = true
            repository.adjustStock(request).fold(
                onSuccess = {
                    loadInventory()
                    onSuccess()
                },
                onFailure = { _error.value = it.message }
            )
            _isLoading.value = false
        }
    }
}
