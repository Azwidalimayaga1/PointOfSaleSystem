package com.possystem.app.ui.sales

import androidx.lifecycle.LiveData
import androidx.lifecycle.MutableLiveData
import androidx.lifecycle.ViewModel
import androidx.lifecycle.viewModelScope
import com.possystem.app.data.model.*
import com.possystem.app.data.repository.POSRepository
import kotlinx.coroutines.launch

data class CartItem(
    val product: Product,
    var quantity: Int = 1
)

class SalesViewModel : ViewModel() {
    private val repository = POSRepository()

    private val _isLoading = MutableLiveData(false)
    val isLoading: LiveData<Boolean> = _isLoading

    private val _products = MutableLiveData<List<Product>>()
    val products: LiveData<List<Product>> = _products

    private val _cartItems = MutableLiveData<List<CartItem>>(emptyList())
    val cartItems: LiveData<List<CartItem>> = _cartItems

    private val _discount = MutableLiveData(0.0)
    val discount: LiveData<Double> = _discount

    private val _discountType = MutableLiveData("percentage")
    val discountType: LiveData<String> = _discountType

    private val _error = MutableLiveData<String?>()
    val error: LiveData<String?> = _error

    private val _saleComplete = MutableLiveData<Sale?>()
    val saleComplete: LiveData<Sale?> = _saleComplete

    private val _customers = MutableLiveData<List<Customer>>()
    val customers: LiveData<List<Customer>> = _customers

    val subtotal: LiveData<Double> = MutableLiveData(0.0).apply {
        val items = _cartItems.value ?: emptyList()
        value = items.sumOf { it.product.price * it.quantity }
    }

    val total: LiveData<Double> = MutableLiveData(0.0)

    fun loadProducts(search: String? = null) {
        viewModelScope.launch {
            _isLoading.value = true
            repository.getProducts(search, stock = "active").fold(
                onSuccess = { _products.value = it },
                onFailure = { _error.value = it.message }
            )
            _isLoading.value = false
        }
    }

    fun addToCart(product: Product) {
        val current = _cartItems.value?.toMutableList() ?: mutableListOf()
        val existing = current.find { it.product.id == product.id }
        if (existing != null) {
            existing.quantity++
        } else {
            current.add(CartItem(product))
        }
        _cartItems.value = current
        recalculate()
    }

    fun updateQuantity(productId: Int, quantity: Int) {
        val current = _cartItems.value?.toMutableList() ?: return
        val item = current.find { it.product.id == productId } ?: return
        if (quantity <= 0) {
            current.remove(item)
        } else {
            item.quantity = quantity
        }
        _cartItems.value = current
        recalculate()
    }

    fun removeFromCart(productId: Int) {
        val current = _cartItems.value?.toMutableList() ?: return
        current.removeAll { it.product.id == productId }
        _cartItems.value = current
        recalculate()
    }

    fun setDiscount(discount: Double, type: String) {
        _discount.value = discount
        _discountType.value = type
        recalculate()
    }

    fun clearCart() {
        _cartItems.value = emptyList()
        _discount.value = 0.0
        _discountType.value = "percentage"
        _saleComplete.value = null
        recalculate()
    }

    private fun recalculate() {
        val items = _cartItems.value ?: emptyList()
        val sub = items.sumOf { it.product.price * it.quantity }
        val disc = _discount.value ?: 0.0
        val discType = _discountType.value ?: "percentage"

        val discountAmount = if (discType == "percentage") sub * disc / 100 else disc
        val tot = sub - discountAmount

        (subtotal as MutableLiveData).value = sub
        (total as MutableLiveData).value = tot
    }

    fun completeSale(
        paymentMethod: String,
        cashAmount: Double?,
        cardAmount: Double?,
        customerName: String? = null
    ) {
        val items = _cartItems.value ?: return
        if (items.isEmpty()) {
            _error.value = "Cart is empty"
            return
        }

        viewModelScope.launch {
            _isLoading.value = true
            _error.value = null

            val request = CompleteSaleRequest(
                items = items.map { SaleItemRequest(it.product.id, it.quantity, it.product.price) },
                paymentMethod = paymentMethod,
                cashAmount = cashAmount,
                cardAmount = cardAmount,
                discount = _discount.value,
                discountType = _discountType.value,
                customerName = customerName
            )

            repository.completeSale(request).fold(
                onSuccess = { sale ->
                    _saleComplete.value = sale
                    clearCart()
                },
                onFailure = { _error.value = it.message }
            )
            _isLoading.value = false
        }
    }

    fun loadCustomers(search: String? = null) {
        viewModelScope.launch {
            repository.getCustomers(search).fold(
                onSuccess = { _customers.value = it },
                onFailure = { }
            )
        }
    }
}
