package com.possystem.app.ui.products

import androidx.lifecycle.LiveData
import androidx.lifecycle.MutableLiveData
import androidx.lifecycle.ViewModel
import androidx.lifecycle.viewModelScope
import com.possystem.app.data.model.Product
import com.possystem.app.data.model.ProductRequest
import com.possystem.app.data.repository.POSRepository
import kotlinx.coroutines.launch

class ProductsViewModel : ViewModel() {
    private val repository = POSRepository()

    private val _isLoading = MutableLiveData(false)
    val isLoading: LiveData<Boolean> = _isLoading

    private val _products = MutableLiveData<List<Product>>()
    val products: LiveData<List<Product>> = _products

    private val _error = MutableLiveData<String?>()
    val error: LiveData<String?> = _error

    private val _searchQuery = MutableLiveData("")
    val searchQuery: LiveData<String> = _searchQuery

    fun loadProducts(search: String? = null) {
        viewModelScope.launch {
            _isLoading.value = true
            _error.value = null

            repository.getProducts(search).fold(
                onSuccess = { _products.value = it },
                onFailure = { _error.value = it.message }
            )
            _isLoading.value = false
        }
    }

    fun search(query: String) {
        _searchQuery.value = query
        loadProducts(query.ifBlank { null })
    }

    fun createProduct(request: ProductRequest, onSuccess: () -> Unit) {
        viewModelScope.launch {
            _isLoading.value = true
            repository.createProduct(request).fold(
                onSuccess = { loadProducts(); onSuccess() },
                onFailure = { _error.value = it.message }
            )
            _isLoading.value = false
        }
    }

    fun updateProduct(id: Int, request: ProductRequest, onSuccess: () -> Unit) {
        viewModelScope.launch {
            _isLoading.value = true
            repository.updateProduct(id, request).fold(
                onSuccess = { loadProducts(); onSuccess() },
                onFailure = { _error.value = it.message }
            )
            _isLoading.value = false
        }
    }

    fun deleteProduct(id: Int) {
        viewModelScope.launch {
            _isLoading.value = true
            repository.deleteProduct(id).fold(
                onSuccess = { loadProducts() },
                onFailure = { _error.value = it.message }
            )
            _isLoading.value = false
        }
    }
}
